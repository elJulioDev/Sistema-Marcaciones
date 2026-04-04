<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php';
date_default_timezone_set('America/Santiago');
$pdo = db();

// ═══════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function limpiar_numero($v) { return preg_replace('/[^0-9K]/', '', strtoupper(trim((string)$v))); }
function obtener_rut_base($n) { return preg_replace('/[^0-9]/', '', (string)$n); }
function parsear_linea($linea) {
    $linea = trim((string)$linea);
    if ($linea === '') return false;
    $p = preg_split("/\t+/", $linea);
    if (count($p) < 4) return false;
    return ['dpto'=>trim($p[0]),'nombre'=>trim($p[1]),'numero'=>trim($p[2]),'fecha_hora'=>trim($p[3])];
}
function mins_to_time($m) { return sprintf('%02d:%02d:00', floor($m/60), $m%60); }

// ═══════════════════════════════════════════════════════════════════
// BATCH INSERT IGNORE — rendimiento masivo vs INSERT individual
// ═══════════════════════════════════════════════════════════════════
function insertar_lote($pdo, $lote, $idImp) {
    if (empty($lote)) return 0;
    $ph  = implode(',', array_fill(0, count($lote), '(?,?,?,?,?,?,?,?,?)'));
    $sql = "INSERT IGNORE INTO marcaciones
            (id_importacion,dpto,nombre,numero,rut_base,fecha_hora,fecha,hora,hash_registro)
            VALUES $ph";
    $params = [];
    foreach ($lote as $r) {
        array_push($params,
            $idImp, $r['dpto'], $r['nombre'], $r['numero'],
            $r['rut_base'], $r['fecha_hora'], $r['fecha'], $r['hora'], $r['hash']
        );
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount(); // Filas realmente insertadas (IGNORE omite duplicados)
}

// ═══════════════════════════════════════════════════════════════════
// RECALCULAR RESUMEN — solo los pares (rut_base, fecha) afectados
// en lugar de recalcular TODOS los registros del sistema.
// ═══════════════════════════════════════════════════════════════════
function recalcular_parcial($pdo, $pares) {
    if (empty($pares)) return 0;

    $stmtM = $pdo->prepare(
        "SELECT hora FROM marcaciones
         WHERE rut_base=? AND fecha=?
         ORDER BY hora ASC, id ASC"
    );
    $stmtE = $pdo->prepare(
        "SELECT numero, nombre, dpto
         FROM marcaciones WHERE rut_base=? AND fecha=? LIMIT 1"
    );
    $stmtU = $pdo->prepare("
        INSERT INTO marcaciones_resumen
            (rut_base,numero,nombre,dpto,fecha,entrada,salida,total_horas,
             cantidad_marcaciones,estado,observacion,editado_manual)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,0)
        ON DUPLICATE KEY UPDATE
            numero=VALUES(numero), nombre=VALUES(nombre), dpto=VALUES(dpto),
            cantidad_marcaciones=VALUES(cantidad_marcaciones),
            entrada      = IF(editado_manual=1, entrada,      VALUES(entrada)),
            salida       = IF(editado_manual=1, salida,       VALUES(salida)),
            total_horas  = IF(editado_manual=1, total_horas,  VALUES(total_horas)),
            estado       = IF(editado_manual=1, estado,       VALUES(estado)),
            observacion  = IF(editado_manual=1, observacion,  VALUES(observacion))
    ");

    $total = 0;
    foreach ($pares as $par) {
        $rut   = $par['rut_base'];
        $fecha = $par['fecha'];

        $stmtE->execute([$rut, $fecha]);
        $emp = $stmtE->fetch(PDO::FETCH_ASSOC);
        if (!$emp) continue;

        $stmtM->execute([$rut, $fecha]);
        $marcas = $stmtM->fetchAll(PDO::FETCH_ASSOC);
        $n = count($marcas);

        $entrada = $salida = $totalH = null;
        $estado = 'OK'; $obs = '';

        if ($n > 0) $entrada = $marcas[0]['hora'];

        if ($n === 1) {
            $estado = 'INCOMPLETO';
            $obs    = 'Solo existe una marcación en el día.';
        } elseif ($n >= 2) {
            $salida = $marcas[$n-1]['hora'];
            $ts1    = strtotime($fecha.' '.$entrada);
            $ts2    = strtotime($fecha.' '.$salida);
            $dif    = intval(($ts2-$ts1)/60);
            if ($dif < 0) {
                $estado = 'ERROR';
                $obs    = 'La salida calculada es anterior a la entrada.';
            } else {
                $totalH = mins_to_time($dif);
                if ($n > 2) {
                    $estado = 'OBSERVADO';
                    $obs    = "Día con $n marcaciones. Revisar detalle.";
                }
            }
        }

        $stmtU->execute([
            $rut, $emp['numero'], $emp['nombre'], $emp['dpto'],
            $fecha, $entrada, $salida, $totalH, $n, $estado, $obs
        ]);
        $total++;
    }
    return $total;
}

// ═══════════════════════════════════════════════════════════════════
// AJAX — STREAMING NDJSON (barra de progreso en tiempo real)
// ═══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'importar') {

    // Desactivar toda la amortiguación de salida
    while (ob_get_level() > 0) ob_end_clean();
    ob_implicit_flush(true);

    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Accel-Buffering: no'); // Desactiva buffer en nginx

    $emit = function($data) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        @flush();
    };

    // ── Validar archivo ──
    if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
        $emit(['phase'=>'error','message'=>'No se recibió ningún archivo.']); exit;
    }
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['txt','csv'])) {
        $emit(['phase'=>'error','message'=>'Solo se permiten archivos .txt o .csv.']); exit;
    }

    $nombreArchivo = $_FILES['archivo']['name'];
    $tmp           = $_FILES['archivo']['tmp_name'];
    $periodo       = isset($_POST['periodo'])     ? trim($_POST['periodo'])     : '';
    $obsImp        = isset($_POST['observacion']) ? trim($_POST['observacion']) : '';
    $creadoPor     = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

    // ── Fase 1: Leer archivo ──
    $emit(['phase'=>'parsing','progress'=>5,'message'=>'Leyendo archivo...']);

    $lineas = file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lineas || count($lineas) <= 1) {
        $emit(['phase'=>'error','message'=>'El archivo está vacío o no contiene datos válidos.']); exit;
    }

    $totalBruto = count($lineas) - 1;
    $emit(['phase'=>'parsing','progress'=>12,'message'=>"Analizando $totalBruto líneas...",'total'=>$totalBruto]);

    // ── Fase 2: Parsear y validar cada línea ──
    $validos = []; $invalidos = 0; $primera = true;

    foreach ($lineas as $linea) {
        if ($primera) { $primera = false; continue; } // Saltar cabecera

        $f = parsear_linea($linea);
        if (!$f) { $invalidos++; continue; }

        $dpto   = $f['dpto'];
        $nombre = $f['nombre'];
        $numero = limpiar_numero($f['numero']);
        $rut    = obtener_rut_base($numero);

        if (!$dpto || !$nombre || !$numero || !$rut || !$f['fecha_hora']) {
            $invalidos++; continue;
        }

        $dt = DateTime::createFromFormat('d/m/Y H:i', $f['fecha_hora']);
        if (!$dt) { $invalidos++; continue; }

        $fh    = $dt->format('Y-m-d H:i:s');
        $fecha = $dt->format('Y-m-d');
        $hora  = $dt->format('H:i:s');
        $hash  = md5(strtoupper($dpto).'|'.strtoupper($nombre).'|'.$numero.'|'.$fh);

        $validos[] = [
            'dpto'=>$dpto,'nombre'=>$nombre,'numero'=>$numero,
            'rut_base'=>$rut,'fecha_hora'=>$fh,'fecha'=>$fecha,'hora'=>$hora,'hash'=>$hash
        ];
    }

    $nValidos = count($validos);
    $emit(['phase'=>'parsed','progress'=>28,'validos'=>$nValidos,'invalidos'=>$invalidos,
           'message'=>"$nValidos válidos · $invalidos inválidos"]);

    if ($nValidos === 0) {
        $emit(['phase'=>'error','message'=>"No se encontraron filas válidas ($invalidos líneas descartadas)."]); exit;
    }

    // ── Fase 3: Inserción en lotes ──
    $pdo->beginTransaction();

    try {
        // Registro de importación
        $stmtI = $pdo->prepare("
            INSERT INTO marcaciones_importaciones
            (nombre_archivo,periodo,observacion,total_lineas,total_insertadas,total_duplicadas,total_invalidas,creado_por)
            VALUES(?,?,?,0,0,0,0,?)
        ");
        $stmtI->execute([$nombreArchivo, ($periodo ?: null), ($obsImp ?: null), ($creadoPor ?: null)]);
        $idImp = (int)$pdo->lastInsertId();

        // Inserción en lotes de 300 filas → reduce 4000 queries a ≈14
        $BATCH    = 300;
        $nBatches = max(1, (int)ceil($nValidos / $BATCH));
        $insertados = $duplicados = 0;
        $afectadas = []; // Pares únicos (rut_base, fecha)

        for ($b = 0; $b < $nBatches; $b++) {
            $lote = array_slice($validos, $b * $BATCH, $BATCH);
            $ins  = insertar_lote($pdo, $lote, $idImp);
            $insertados += $ins;
            $duplicados += count($lote) - $ins;

            // Registrar pares afectados (para recalcular solo esos)
            foreach ($lote as $row) {
                $key = $row['rut_base'].'|'.$row['fecha'];
                $afectadas[$key] = ['rut_base'=>$row['rut_base'],'fecha'=>$row['fecha']];
            }

            $prog = 28 + (int)round((($b+1)/$nBatches) * 50);
            $emit([
                'phase'       => 'inserting',
                'progress'    => $prog,
                'batch'       => $b+1,
                'totalBatches'=> $nBatches,
                'inserted'    => $insertados,
                'duplicated'  => $duplicados,
                'message'     => "Lote ".($b+1)."/$nBatches insertado — $insertados nuevos, $duplicados duplicados"
            ]);
        }

        // ── Fase 4: Recalcular resumen parcial ──
        $nPares = count($afectadas);
        $emit(['phase'=>'resumen','progress'=>80,'pairs'=>$nPares,
               'message'=>"Calculando $nPares resúmenes de asistencia..."]);

        $recalc = recalcular_parcial($pdo, array_values($afectadas));

        // Actualizar registro de importación con los totales reales
        $pdo->prepare("
            UPDATE marcaciones_importaciones
            SET total_lineas=?,total_insertadas=?,total_duplicadas=?,total_invalidas=?
            WHERE id=?
        ")->execute([$totalBruto, $insertados, $duplicados, $invalidos, $idImp]);

        $pdo->commit();

        $emit([
            'phase'   => 'done',
            'progress'=> 100,
            'result'  => [
                'leidos'     => $totalBruto,
                'validos'    => $nValidos,
                'insertados' => $insertados,
                'duplicados' => $duplicados,
                'invalidos'  => $invalidos,
                'resumen'    => $recalc,
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        $emit(['phase'=>'error','message'=>'Error interno: '.$e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Importar Marcaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset & base ─────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f1f5f9;--s1:#ffffff;--s2:#f8fafc;--s3:#e2e8f0;
  --b0:#e2e8f0;--b1:#cbd5e1;--b2:#94a3b8;
  --t1:#0f172a;--t2:#475569;--t3:#64748b;
  --blue:#2563eb;--blg:rgba(37,99,235,.12);--bls:rgba(37,99,235,.06);
  --grn:#059669;--gng:rgba(5,150,105,.12);
  --amb:#d97706;--amg:rgba(217,119,6,.12);
  --red:#dc2626;--rdg:rgba(220,38,38,.12);
  --r:14px;--r2:8px;
  --font:'Figtree',system-ui,sans-serif;
  --mono:'JetBrains Mono','Courier New',monospace;
}
body{font-family:var(--font);background:var(--bg);color:var(--t1);
     height:100vh;display:flex;flex-direction:column;overflow:hidden;line-height:1.5;}

.main-scroll{flex:1;overflow-y:auto;width:100%;}
.wrap {
  max-width: 820px;
  margin: 0 auto;
  padding: 16px 20px;
}
/* ── Card base ─────────────────────────────────────────────── */
.card {
  background: var(--s1);
  border-radius: var(--r);
  border: 1px solid var(--b0);
  box-shadow: 0 4px 6px -1px rgba(0,0,0,.05), 0 2px 4px -2px rgba(0,0,0,.04);
  padding: 20px;
  margin-bottom: 14px;
}
.card-hd {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}
.card-hd h1{font-size:21px;font-weight:700;color:var(--t1);letter-spacing:-.3px;}
.card-sub {
  font-size: 13.5px;
  color: var(--t3);
  line-height: 1.5;
  margin-bottom: 16px;
}
.card-sub strong{color:var(--t2);}

/* ── Alert ─────────────────────────────────────────────────── */
.alert-err{display:none;align-items:flex-start;gap:10px;background:var(--rdg);
           border:1px solid rgba(220,38,38,.25);border-radius:var(--r2);
           padding:12px 14px;font-size:13px;color:var(--red);margin-bottom:16px;}
.alert-err.show{display:flex;}
.alert-err svg{flex-shrink:0;margin-top:1px;}

/* ── Dropzone ──────────────────────────────────────────────── */
.dropzone {
  position: relative;
  border: 2px dashed var(--b1);
  border-radius: var(--r);
  padding: 20px 16px;
  text-align: center;
  cursor: pointer;
  transition: .2s;
  background: var(--s2);
  margin-bottom: 16px;
}
.dropzone:hover,.dropzone.drag-over{border-color:var(--blue);background:var(--blg);}
.dropzone.has-file{border-style:solid;border-color:var(--grn);background:var(--gng);}
.dropzone input[type="file"]{
  position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;
}
.dz-icon {
  width: 42px;
  height: 42px;
  border-radius: var(--r);
  margin: 0 auto 10px;
  background: var(--blg);
  color: var(--blue);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: .2s;
}
.dropzone.has-file .dz-icon{background:var(--gng);color:var(--grn);}
.dz-title{font-size:15px;font-weight:700;color:var(--t1);margin-bottom:4px;
          transition:.15s;}
.dz-sub{font-size:13px;color:var(--t3);}
.dz-chips {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 10px;
}
.dz-chip{
  display:inline-flex;align-items:center;gap:5px;padding:5px 12px;
  border-radius:999px;font-size:12px;font-weight:600;
  background:var(--gng);color:var(--grn);border:1px solid rgba(5,150,105,.2);
}

/* ── Form grid ─────────────────────────────────────────────── */
.form-grid {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 12px;
  margin-bottom: 16px;
}
.fg label {
  display: block;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--t3);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 4px;
}
.fg input, .fg textarea {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--b0);
  border-radius: var(--r2);
  font-family: var(--font);
  font-size: 14px;
  color: var(--t1);
  background: var(--s1);
  transition: .15s;
  outline: none;
}
.fg input:focus,.fg textarea:focus{
  border-color:var(--blue);box-shadow:0 0 0 3px var(--blg);
}
.fg textarea {
  resize: vertical;
  min-height: 48px;
}

/* ── Import button ─────────────────────────────────────────── */
.btn-import {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 11px 20px;
  border: none;
  border-radius: var(--r2);
  font-family: var(--font);
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: .2s;
}
.btn-import:not(:disabled){
  background:var(--blue);color:#fff;
  box-shadow:0 4px 12px rgba(37,99,235,.25);
}
.btn-import:not(:disabled):hover{
  background:#1d4ed8;transform:translateY(-1px);
  box-shadow:0 6px 18px rgba(37,99,235,.35);
}
.btn-import:disabled{
  background:var(--s3);color:var(--b2);cursor:not-allowed;
  box-shadow:none;
}

/* ── Progress card ─────────────────────────────────────────── */
.prog-card{display:none;}
.prog-card.show{display:block;}

.prog-header { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
.prog-spinner{
  width:40px;height:40px;border:3px solid var(--b0);
  border-top-color:var(--blue);border-radius:50%;
  animation:spin .75s linear infinite;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.prog-spinner.done{animation:none;border-color:var(--grn);background:var(--gng);}
.prog-spinner.err{ animation:none;border-color:var(--red); background:var(--rdg);}
@keyframes spin{to{transform:rotate(360deg)}}
.prog-title{font-size:17px;font-weight:700;color:var(--t1);}
.prog-sub{font-size:13px;color:var(--t3);margin-top:2px;}

/* ── Progress bar ──────────────────────────────────────────── */
.pbar-wrap { margin-bottom: 16px; }
.pbar-meta{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;}
.pbar-msg{font-size:13px;color:var(--t2);font-weight:500;}
.pbar-pct{font-size:13px;font-weight:700;color:var(--blue);font-family:var(--mono);}
.pbar-track{height:10px;background:var(--b0);border-radius:999px;overflow:hidden;}
.pbar-fill{
  height:100%;width:0%;border-radius:999px;
  background:linear-gradient(90deg,var(--blue) 0%,#38bdf8 100%);
  background-size:200% 100%;
  animation:pbar-shimmer 1.8s linear infinite;
  transition:width .45s cubic-bezier(.4,0,.2,1);
}
.pbar-fill.done{
  background:linear-gradient(90deg,var(--grn),#34d399);
  animation:none;
}
.pbar-fill.err{background:var(--red);animation:none;}
@keyframes pbar-shimmer{
  0%{background-position:100% 0}100%{background-position:-100% 0}
}

/* ── Steps ─────────────────────────────────────────────────── */
.steps{display:flex;flex-direction:column;gap:8px;}
.step {
  display: flex; align-items: center; gap: 12px;
  padding: 9px 12px;
  border-radius: var(--r2); border: 1px solid var(--b0); background: var(--s2);
  font-size: 13px; font-weight: 500; color: var(--t2); transition: .25s;
}
.step.active{background:var(--blg);border-color:rgba(37,99,235,.25);color:#1e40af;font-weight:600;}
.step.done  {background:var(--gng);border-color:rgba(5,150,105,.25);color:#065f46;}
.step.err   {background:var(--rdg);border-color:rgba(220,38,38,.25);color:var(--red);}

.step-num{
  width:26px;height:26px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;
  background:var(--s3);color:var(--t2);transition:.25s;
}
.step.active .step-num{background:var(--blue);color:#fff;}
.step.done   .step-num{background:var(--grn); color:#fff;}
.step.err    .step-num{background:var(--red);  color:#fff;}

.step-text{flex:1;min-width:0;}
.step-badge{
  font-family:var(--mono);font-size:11px;font-weight:700;
  padding:3px 9px;border-radius:4px;white-space:nowrap;
  background:var(--blg);color:var(--blue);
  display:none;
}
.step.done .step-badge{background:var(--gng);color:var(--grn);display:inline;}

/* ── Results card ──────────────────────────────────────────── */
.res-card{display:none;}
.res-card.show{display:block;}

.res-hd{display:flex;align-items:center;gap:10px;margin-bottom:6px;}
.res-hd h2{font-size:19px;font-weight:700;color:var(--grn);}
.res-sub { font-size: 13px; color: var(--t3); margin-bottom: 16px; }
.res-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px; margin-bottom: 16px;
}
.res-stat {
  background: var(--s2); border: 1px solid var(--b0); border-radius: var(--r2);
  padding: 12px 10px;
  text-align: center;
}
.res-val {
  font-size: 26px;
  font-weight: 700; font-family: var(--mono);
  line-height: 1; margin-bottom: 4px;
}
.res-lbl{font-size:10.5px;font-weight:700;color:var(--t3);
         text-transform:uppercase;letter-spacing:.06em;}
.res-stat.grn{border-color:rgba(5,150,105,.3); background:var(--gng);}
.res-stat.grn .res-val{color:var(--grn);}
.res-stat.blu{border-color:rgba(37,99,235,.3); background:var(--blg);}
.res-stat.blu .res-val{color:var(--blue);}
.res-stat.amb{border-color:rgba(217,119,6,.3); background:var(--amg);}
.res-stat.amb .res-val{color:var(--amb);}
.res-stat.red{border-color:rgba(220,38,38,.3); background:var(--rdg);}
.res-stat.red .res-val{color:var(--red);}
.res-stat.slt{border-color:var(--b1); background:var(--s3);}
.res-stat.slt .res-val{color:var(--t2);}

.btn-group{display:flex;gap:10px;flex-wrap:wrap;}
.btn-sec{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 18px;border-radius:var(--r2);
  font-family:var(--font);font-size:13.5px;font-weight:600;
  cursor:pointer;text-decoration:none;transition:.15s;
  border:1px solid var(--b1);background:var(--s1);color:var(--t2);
}
.btn-sec:hover{background:var(--s3);color:var(--t1);border-color:var(--b2);}
.btn-sec.primary{background:var(--blue);color:#fff;border-color:var(--blue);}
.btn-sec.primary:hover{background:#1d4ed8;}

/* ── Historial últimas importaciones ───────────────────────── */
.hist-card{display:none;}

/* ── Responsive ────────────────────────────────────────────── */
@media(max-width:600px){
  .form-grid { grid-template-columns: 1fr; }
  .wrap { padding: 12px 14px 20px; }
  .card { padding: 16px; }
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="main-scroll">
<div class="wrap">

<!-- ═══ UPLOAD CARD ═══════════════════════════════════════ -->
<div class="card" id="upload-card">
  <div class="card-hd">
    <svg width="26" height="26" fill="none" stroke="var(--blue)" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="17 8 12 3 7 8"/>
      <line x1="12" y1="3" x2="12" y2="15"/>
    </svg>
    <h1>Importar Marcaciones</h1>
  </div>
  <p class="card-sub">
    Sube el archivo <strong>TXT o CSV</strong> exportado desde el reloj de control.
    Los registros duplicados se detectan y omiten automáticamente usando un hash
    por registro. La inserción se realiza en lotes para mayor rendimiento.
  </p>

  <div class="alert-err" id="alert-err">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
      <line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span id="alert-err-msg"></span>
  </div>

  <!-- Dropzone -->
  <div class="dropzone" id="dropzone">
    <input type="file" id="file-input" accept=".txt,.csv">
    <div class="dz-icon" id="dz-icon">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
           id="dz-svg">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
    </div>
    <div class="dz-title" id="dz-title">Arrastra tu archivo aquí</div>
    <div class="dz-sub"   id="dz-sub">o haz clic para seleccionar &nbsp;·&nbsp; .TXT o .CSV</div>
    <div class="dz-chips" id="dz-chips" style="display:none;">
      <span class="dz-chip" id="dz-chip-name"></span>
      <span class="dz-chip" id="dz-chip-size"></span>
    </div>
  </div>

  <!-- Período + Observación -->
  <div class="form-grid">
    <div class="fg">
      <label for="periodo">Período</label>
      <input type="month" id="periodo" placeholder="YYYY-MM">
    </div>
    <div class="fg">
      <label for="observacion">Observación de la carga</label>
      <textarea id="observacion" placeholder="Ej: Marcaciones marzo 2025 · Carga corregida..."></textarea>
    </div>
  </div>

  <button class="btn-import" id="btn-import" disabled>
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
    </svg>
    <span id="btn-import-text">Selecciona un archivo para continuar</span>
  </button>
</div>

<!-- ═══ PROGRESS CARD ══════════════════════════════════════ -->
<div class="card prog-card" id="prog-card">

  <div class="prog-header">
    <div class="prog-spinner" id="prog-spinner"></div>
    <div>
      <div class="prog-title" id="prog-title">Procesando importación...</div>
      <div class="prog-sub"   id="prog-sub">Esto puede tardar algunos segundos.</div>
    </div>
  </div>

  <div class="pbar-wrap">
    <div class="pbar-meta">
      <span class="pbar-msg" id="pbar-msg">Iniciando...</span>
      <span class="pbar-pct" id="pbar-pct">0 %</span>
    </div>
    <div class="pbar-track">
      <div class="pbar-fill" id="pbar-fill"></div>
    </div>
  </div>

  <div class="steps">
    <div class="step" id="step-1">
      <div class="step-num" data-n="1">1</div>
      <div class="step-text">Leyendo y validando el archivo</div>
      <span class="step-badge" id="badge-1"></span>
    </div>
    <div class="step" id="step-2">
      <div class="step-num" data-n="2">2</div>
      <div class="step-text">Insertando registros en la base de datos</div>
      <span class="step-badge" id="badge-2"></span>
    </div>
    <div class="step" id="step-3">
      <div class="step-num" data-n="3">3</div>
      <div class="step-text">Calculando resúmenes de asistencia</div>
      <span class="step-badge" id="badge-3"></span>
    </div>
  </div>

</div>

<!-- ═══ RESULTS CARD ═══════════════════════════════════════ -->
<div class="card res-card" id="res-card">
  <div class="res-hd">
    <svg width="22" height="22" fill="none" stroke="var(--grn)" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
      <polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <h2>¡Importación completada!</h2>
  </div>
  <p class="res-sub">Los datos fueron procesados e integrados al sistema correctamente.</p>

  <div class="res-grid" id="res-grid"></div>

  <div class="btn-group">
    <button class="btn-sec primary" id="btn-again">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <polyline points="23 4 23 10 17 10"/>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
      </svg>
      Nueva importación
    </button>
    <a class="btn-sec" href="calendario_marcaciones.php">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <rect x="3" y="4" width="18" height="18" rx="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      Ver calendario
    </a>
    <a class="btn-sec" href="observaciones_marcaciones.php">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      Ver observaciones
    </a>
  </div>
</div>

</div><!-- /wrap -->
</div><!-- /main-scroll -->

<script>
(function(){
'use strict';

/* ── DOM ──────────────────────────────────────────────────── */
var dropzone   = document.getElementById('dropzone');
var fileInput  = document.getElementById('file-input');
var btnImport  = document.getElementById('btn-import');
var btnImportTxt = document.getElementById('btn-import-text');
var alertErr   = document.getElementById('alert-err');
var alertMsg   = document.getElementById('alert-err-msg');

var uploadCard = document.getElementById('upload-card');
var progCard   = document.getElementById('prog-card');
var resCard    = document.getElementById('res-card');

var progSpinner = document.getElementById('prog-spinner');
var progTitle   = document.getElementById('prog-title');
var progSub     = document.getElementById('prog-sub');
var pbarFill    = document.getElementById('pbar-fill');
var pbarMsg     = document.getElementById('pbar-msg');
var pbarPct     = document.getElementById('pbar-pct');

var selectedFile = null;

/* ── Utilidades ───────────────────────────────────────────── */
function fmtBytes(b){
    if(b<1024) return b+' B';
    if(b<1048576) return (b/1024).toFixed(1)+' KB';
    return (b/1048576).toFixed(2)+' MB';
}
function fmtNum(n){ return parseInt(n).toLocaleString('es-CL'); }

/* ── Alertas ──────────────────────────────────────────────── */
function showAlert(msg){ alertMsg.textContent=msg; alertErr.classList.add('show'); }
function hideAlert(){ alertErr.classList.remove('show'); }

/* ── Dropzone ─────────────────────────────────────────────── */
function setFile(file){
    if(!file) return;
    var ext = file.name.split('.').pop().toLowerCase();
    if(ext!=='txt' && ext!=='csv'){
        showAlert('Solo se permiten archivos .txt o .csv'); return;
    }
    hideAlert();
    selectedFile = file;
    dropzone.classList.add('has-file');
    document.getElementById('dz-title').textContent = file.name;
    document.getElementById('dz-sub').textContent   = 'Archivo listo para importar';
    document.getElementById('dz-chips').style.display = 'flex';
    document.getElementById('dz-chip-name').textContent = '📄 '+file.name;
    document.getElementById('dz-chip-size').textContent = '💾 '+fmtBytes(file.size);
    // Icono check
    document.getElementById('dz-svg').innerHTML =
        '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
    btnImport.disabled = false;
    btnImportTxt.textContent = 'Importar  ' + file.name;
}

function resetDropzone(){
    selectedFile = null;
    fileInput.value = '';
    dropzone.classList.remove('has-file');
    document.getElementById('dz-title').textContent = 'Arrastra tu archivo aquí';
    document.getElementById('dz-sub').textContent   = 'o haz clic para seleccionar · .TXT o .CSV';
    document.getElementById('dz-chips').style.display = 'none';
    document.getElementById('dz-svg').innerHTML =
        '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>';
    btnImport.disabled = true;
    btnImportTxt.textContent = 'Selecciona un archivo para continuar';
}

fileInput.addEventListener('change', function(){
    if(this.files && this.files[0]) setFile(this.files[0]);
});
dropzone.addEventListener('dragover', function(e){
    e.preventDefault(); this.classList.add('drag-over');
});
dropzone.addEventListener('dragleave', function(){
    this.classList.remove('drag-over');
});
dropzone.addEventListener('drop', function(e){
    e.preventDefault(); this.classList.remove('drag-over');
    if(e.dataTransfer.files && e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
});

/* ── Steps ────────────────────────────────────────────────── */
function setStep(n, state, badge){
    var el  = document.getElementById('step-'+n);
    var num = el.querySelector('.step-num');
    var bdg = document.getElementById('badge-'+n);
    el.className = 'step'+(state?' '+state:'');
    if(state==='done')     num.innerHTML = '&#10003;';
    else if(state==='err') num.innerHTML = '&#10007;';
    else                   num.textContent = num.dataset.n;
    if(badge && bdg){ bdg.textContent = badge; bdg.style.display='inline'; }
}

/* ── Progress bar ─────────────────────────────────────────── */
function setProg(pct, msg, state){
    pct = Math.max(0, Math.min(100, pct));
    pbarFill.style.width = pct+'%';
    pbarPct.textContent  = pct+' %';
    if(msg) pbarMsg.textContent = msg;
    if(state==='done'){ pbarFill.className='pbar-fill done'; }
    else if(state==='err'){ pbarFill.className='pbar-fill err'; }
    else { pbarFill.className='pbar-fill'; }
}

function resetProgress(){
    setStep(1,''); setStep(2,''); setStep(3,'');
    setProg(0,'Iniciando...');
    progSpinner.className = 'prog-spinner';
    progSpinner.innerHTML = '';
    progTitle.textContent = 'Procesando importación...';
    progSub.textContent   = 'Esto puede tardar algunos segundos.';
    document.getElementById('badge-1').style.display = 'none';
    document.getElementById('badge-2').style.display = 'none';
    document.getElementById('badge-3').style.display = 'none';
}

/* ── Event handler ────────────────────────────────────────── */
function handleEvent(ev){
    switch(ev.phase){

        case 'parsing':
            setStep(1,'active');
            setProg(ev.progress, ev.message||'Leyendo archivo...');
            break;

        case 'parsed':
            setStep(1,'done', fmtNum(ev.validos)+' válidos · '+fmtNum(ev.invalidos)+' inv.');
            setStep(2,'active');
            setProg(ev.progress, ev.message||'Preparando inserción...');
            progSub.textContent = fmtNum(ev.validos)+' registros válidos para insertar.';
            break;

        case 'inserting':
            setStep(2,'active');
            setProg(ev.progress, 'Lote '+ev.batch+' de '+ev.totalBatches+
                    ' — '+fmtNum(ev.inserted)+' insertados, '+fmtNum(ev.duplicated)+' duplicados');
            document.getElementById('badge-2').textContent =
                fmtNum(ev.inserted)+' ins. · '+fmtNum(ev.duplicated)+' dup.';
            document.getElementById('badge-2').style.display = 'inline';
            progSub.textContent = 'Lote '+ev.batch+'/'+ev.totalBatches+
                ' — '+fmtNum(ev.inserted)+' registros nuevos en BD.';
            break;

        case 'resumen':
            setStep(2,'done');
            setStep(3,'active');
            setProg(ev.progress, ev.message||'Calculando resúmenes...');
            if(ev.pairs){
                document.getElementById('badge-3').textContent = ev.pairs+' pares';
                document.getElementById('badge-3').style.display = 'inline';
            }
            progSub.textContent = 'Actualizando tabla de resúmenes de asistencia...';
            break;

        case 'done':
            setStep(3,'done');
            setProg(100,'¡Importación completada exitosamente!','done');
            progTitle.textContent = '¡Completado!';
            progSub.textContent   = 'Todos los datos fueron procesados.';
            progSpinner.className = 'prog-spinner done';
            progSpinner.innerHTML =
                '<svg width="18" height="18" fill="none" stroke="var(--grn)" stroke-width="3"'+
                ' stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">'+
                '<polyline points="20 6 9 17 4 12"/></svg>';
            setTimeout(function(){ showResults(ev.result); }, 700);
            break;

        case 'error':
            setProg(pbarFill.style.width ? parseInt(pbarFill.style.width) : 0,
                    'Error en la importación.', 'err');
            progTitle.textContent = 'Error al importar';
            progSub.textContent   = ev.message||'Ocurrió un error inesperado.';
            progSpinner.className = 'prog-spinner err';
            progSpinner.innerHTML =
                '<svg width="18" height="18" fill="none" stroke="var(--red)" stroke-width="3"'+
                ' stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">'+
                '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            setTimeout(function(){
                progCard.classList.remove('show');
                uploadCard.style.display = '';
                showAlert(ev.message||'Error desconocido al importar.');
            }, 2000);
            break;
    }
}

/* ── Results ──────────────────────────────────────────────── */
function showResults(r){
    progCard.classList.remove('show');
    var grid = document.getElementById('res-grid');
    function stat(val, lbl, cls){
        return '<div class="res-stat '+cls+'">'+
               '<div class="res-val">'+fmtNum(val)+'</div>'+
               '<div class="res-lbl">'+lbl+'</div></div>';
    }
    grid.innerHTML =
        stat(r.leidos,     'Líneas leídas',        'slt') +
        stat(r.insertados, 'Nuevos registros',      'grn') +
        stat(r.duplicados, 'Duplicados omitidos',   r.duplicados>0?'amb':'slt') +
        stat(r.invalidos,  'Líneas inválidas',      r.invalidos>0?'red':'slt') +
        stat(r.resumen,    'Resúmenes recalculados','blu');
    resCard.classList.add('show');
}

/* ── Import trigger ───────────────────────────────────────── */
btnImport.addEventListener('click', function(){
    if(!selectedFile || btnImport.disabled) return;
    hideAlert();

    var fd = new FormData();
    fd.append('archivo',    selectedFile);
    fd.append('periodo',    document.getElementById('periodo').value);
    fd.append('observacion',document.getElementById('observacion').value);

    // Mostrar progreso, ocultar formulario
    uploadCard.style.display = 'none';
    resCard.classList.remove('show');
    progCard.classList.add('show');
    resetProgress();

    // Fetch con streaming NDJSON
    fetch('?action=importar', {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(response){
        if(!response.ok) throw new Error('HTTP '+response.status);

        // Fallback para navegadores sin ReadableStream
        if(!response.body){
            return response.text().then(function(text){
                text.split('\n').forEach(function(line){
                    if(line.trim()){ try{ handleEvent(JSON.parse(line)); }catch(e){} }
                });
            });
        }

        // Lectura en streaming
        var reader  = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer  = '';

        function read(){
            return reader.read().then(function(chunk){
                if(chunk.done){
                    if(buffer.trim()){ try{ handleEvent(JSON.parse(buffer)); }catch(e){} }
                    return;
                }
                buffer += decoder.decode(chunk.value, {stream:true});
                var lines = buffer.split('\n');
                buffer = lines.pop();
                lines.forEach(function(line){
                    if(line.trim()){ try{ handleEvent(JSON.parse(line)); }catch(e){} }
                });
                return read();
            });
        }
        return read();
    })
    .catch(function(err){
        handleEvent({phase:'error', message:'Error de conexión: '+err.message});
    });
});

/* ── Nueva importación ────────────────────────────────────── */
document.getElementById('btn-again').addEventListener('click', function(){
    resCard.classList.remove('show');
    resetDropzone();
    hideAlert();
    uploadCard.style.display = '';
});

}());
</script>
</body>
</html>