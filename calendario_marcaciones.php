<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php';
date_default_timezone_set('America/Santiago');
$pdo = db();

/* ─── helpers ────────────────────────────────────────────── */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ─── params ─────────────────────────────────────────────── */
$hoy    = date('Y-m-d');
$mesDef = date('Y-m');
$isJson = isset($_GET['json']) && $_GET['json'] === '1';

$mes = (isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', trim($_GET['mes'])))
         ? trim($_GET['mes']) : $mesDef;
$modo = (isset($_GET['modo']) && $_GET['modo'] === 'semana') ? 'semana' : 'dia';
$fechaSel = (isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($_GET['fecha'])))
              ? trim($_GET['fecha'])
              : ($mes === $mesDef ? $hoy : $mes.'-01');
if (substr($fechaSel, 0, 7) !== $mes) $fechaSel = $mes.'-01';

// Nuevos parámetros de filtros
$f_dpto   = isset($_GET['dpto']) ? trim($_GET['dpto']) : '';
$f_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$f_q      = isset($_GET['q']) ? trim($_GET['q']) : '';

/* ─── labels ─────────────────────────────────────────────── */
$MESES  = array('','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre');
$DIAS   = array('','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo');
$DIAS_C = array('Lun','Mar','Mié','Jue','Vie','Sáb','Dom');

/* ─── month meta ─────────────────────────────────────────── */
$mesDate   = new DateTime($mes.'-01');
$tmpPrev   = clone $mesDate;
$mesPrev   = $tmpPrev->modify('-1 month')->format('Y-m');
$tmpNext   = clone $mesDate;
$mesNext   = $tmpNext->modify('+1 month')->format('Y-m');
$mesLabel  = $MESES[(int)$mesDate->format('n')].' '.$mesDate->format('Y');
$diasEnMes = (int)$mesDate->format('t');
$primerDOW = (int)$mesDate->format('N');

/* ─── Departamentos disponibles ──────────────────────────── */
$stmtDptos = $pdo->query("SELECT DISTINCT dpto FROM marcaciones_resumen WHERE dpto != '' ORDER BY dpto");
$dptosDisponibles = $stmtDptos->fetchAll(PDO::FETCH_COLUMN);

/* ─── calendar dots ──────────────────────────────────────── */
$whereD = "DATE_FORMAT(fecha,'%Y-%m') = :mes";
$paramsD = array(':mes' => $mes);
if ($f_dpto !== '') { $whereD .= " AND dpto = :dpto"; $paramsD[':dpto'] = $f_dpto; }
if ($f_estado !== '') { $whereD .= " AND estado = :estado"; $paramsD[':estado'] = $f_estado; }
if ($f_q !== '') {
    $whereD .= " AND (nombre LIKE :q1 OR rut_base LIKE :q2 OR numero LIKE :q3)";
    $paramsD[':q1'] = "%$f_q%";
    $paramsD[':q2'] = "%$f_q%";
    $paramsD[':q3'] = "%$f_q%";
}

$stmtD = $pdo->prepare("
    SELECT fecha,
           COUNT(*) AS total,
           SUM(CASE WHEN estado='OK' THEN 1 ELSE 0 END) AS ok_cnt,
           SUM(CASE WHEN estado<>'OK' THEN 1 ELSE 0 END) AS issue_cnt
    FROM marcaciones_resumen
    WHERE $whereD
    GROUP BY fecha
");
$stmtD->execute($paramsD);
$dots = array();
foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $r) $dots[$r['fecha']] = $r;

/* ─── stats (dinámico mes/semana) ────────────────────────── */
$whereSt = $modo === 'semana' ? "fecha IN" : "DATE_FORMAT(fecha,'%Y-%m') = ?";
$paramsSt = array();

if ($modo === 'semana') {
    $selDate = new DateTime($fechaSel);
    $selDOW  = (int)$selDate->format('N');
    
    $lunes = clone $selDate;
    $lunes->modify('-'.($selDOW-1).' days');
    
    $fechasSemStats = array();
    for ($i = 0; $i < 7; $i++) {
        $tmpDia = clone $lunes;
        $fechasSemStats[] = $tmpDia->modify("+$i days")->format('Y-m-d');
    }
    $phStats = implode(',', array_fill(0, 7, '?'));
    $whereSt = "fecha IN ($phStats)";
    $paramsSt = $fechasSemStats;
} else {
    $whereSt = "DATE_FORMAT(fecha,'%Y-%m') = ?";
    $paramsSt = array($mes);
}

if ($f_dpto !== '') { $whereSt .= " AND dpto = ?"; $paramsSt[] = $f_dpto; }
if ($f_estado !== '') { $whereSt .= " AND estado = ?"; $paramsSt[] = $f_estado; }
if ($f_q !== '') {
    $whereSt .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)";
    $paramsSt[] = "%$f_q%"; $paramsSt[] = "%$f_q%"; $paramsSt[] = "%$f_q%";
}

$stmtSt = $pdo->prepare("
    SELECT COUNT(DISTINCT rut_base) AS empleados,
           COUNT(DISTINCT fecha)    AS dias_datos,
           SUM(CASE WHEN estado='OK' THEN 1 ELSE 0 END) AS ok_total,
           SUM(CASE WHEN estado='INCOMPLETO' THEN 1 ELSE 0 END) AS inc_total,
           SUM(CASE WHEN estado='OBSERVADO' THEN 1 ELSE 0 END) AS obs_total,
           SUM(CASE WHEN estado='ERROR' THEN 1 ELSE 0 END) AS err_total
    FROM marcaciones_resumen WHERE $whereSt
");
$stmtSt->execute($paramsSt);
$stRaw = $stmtSt->fetch(PDO::FETCH_ASSOC);
$st    = $stRaw ? $stRaw : array('empleados'=>0,'dias_datos'=>0,'ok_total'=>0,'inc_total'=>0,'obs_total'=>0,'err_total'=>0);

/* ─── presentes ──────────────────────────────────────────── */
$sqlP = "
    SELECT id, rut_base, numero, nombre, dpto, entrada, salida, total_horas,
           cantidad_marcaciones, estado, observacion, editado_manual
    FROM marcaciones_resumen WHERE fecha = :f
";
$paramsP = array(':f' => $fechaSel);
if ($f_dpto !== '') { $sqlP .= " AND dpto = :dpto"; $paramsP[':dpto'] = $f_dpto; }
if ($f_estado !== '') { $sqlP .= " AND estado = :estado"; $paramsP[':estado'] = $f_estado; }
if ($f_q !== '') {
    $sqlP .= " AND (nombre LIKE :q1 OR rut_base LIKE :q2 OR numero LIKE :q3)";
    $paramsP[':q1'] = "%$f_q%";
    $paramsP[':q2'] = "%$f_q%";
    $paramsP[':q3'] = "%$f_q%";
}
$sqlP .= " ORDER BY nombre ASC";

$stmtP = $pdo->prepare($sqlP);
$stmtP->execute($paramsP);
$presentes = $stmtP->fetchAll(PDO::FETCH_ASSOC);

/* ─── ausentes ───────────────────────────────────────────── */
$sqlA = "
    SELECT DISTINCT rut_base, nombre, dpto, numero
    FROM marcaciones_resumen
    WHERE DATE_FORMAT(fecha,'%Y-%m') = :mes
      AND rut_base NOT IN (SELECT rut_base FROM marcaciones_resumen WHERE fecha = :f)
";
$paramsA = array(':mes' => $mes, ':f' => $fechaSel);
if ($f_dpto !== '') { $sqlA .= " AND dpto = :dpto"; $paramsA[':dpto'] = $f_dpto; }
if ($f_q !== '') {
    $sqlA .= " AND (nombre LIKE :q1 OR rut_base LIKE :q2 OR numero LIKE :q3)";
    $paramsA[':q1'] = "%$f_q%";
    $paramsA[':q2'] = "%$f_q%";
    $paramsA[':q3'] = "%$f_q%";
}
// Si filtramos por un estado específico (ej. ERROR), los ausentes no tienen estado, por lo que no deberían mostrarse.
if ($f_estado !== '') { $sqlA .= " AND 1=0"; }
$sqlA .= " ORDER BY nombre ASC";

$stmtA = $pdo->prepare($sqlA);
$stmtA->execute($paramsA);
$ausentes = $stmtA->fetchAll(PDO::FETCH_ASSOC);

/* ─── week strip ─────────────────────────────────────────── */
$selDate = new DateTime($fechaSel);
$selDOW  = (int)$selDate->format('N');
$lunes = clone $selDate;
$lunes->modify('-'.($selDOW-1).' days');
$semana  = array();
for ($i = 0; $i < 7; $i++) {
    $d = clone $lunes;
    $d->modify("+$i days");
    $fstr = $d->format('Y-m-d');
    $dRaw = isset($dots[$fstr]) ? $dots[$fstr] : null;
    $semana[] = array(
        'fecha'  => $fstr,
        'label'  => $DIAS_C[$i],
        'num'    => (int)$d->format('j'),
        'fin'    => $i >= 5,
        'hoy'    => $fstr === $hoy,
        'en_mes' => substr($fstr,0,7) === $mes,
        'dot'    => $dRaw,
    );
}

/* ─── week matrix ────────────────────────────────────────── */
$matrizEmps = array();
$matrizData = array();
$ausentesSemana = array();

if ($modo === 'semana') {
    $fechasSem = array();
    foreach ($semana as $s) $fechasSem[] = $s['fecha'];
    $ph = implode(',', array_fill(0, 7, '?'));
    
    $whereM = "fecha IN ($ph)";
    $paramsM = $fechasSem;
    if ($f_dpto !== '') { $whereM .= " AND dpto = ?"; $paramsM[] = $f_dpto; }
    if ($f_estado !== '') { $whereM .= " AND estado = ?"; $paramsM[] = $f_estado; }
    if ($f_q !== '') {
        $whereM .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)";
        $paramsM[] = "%$f_q%"; $paramsM[] = "%$f_q%"; $paramsM[] = "%$f_q%";
    }

    $stmtM = $pdo->prepare("
        SELECT id, fecha, rut_base, nombre, dpto, numero,
               entrada, salida, total_horas, cantidad_marcaciones, estado
        FROM marcaciones_resumen
        WHERE $whereM ORDER BY nombre ASC, fecha ASC
    ");
    $stmtM->execute($paramsM);
    $tmpEmps = array();
    foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = $r['rut_base'];
        if (!isset($tmpEmps[$k])) {
            $tmpEmps[$k] = array('nombre'=>$r['nombre'],'dpto'=>$r['dpto'],'numero'=>$r['numero'],'rut'=>$k);
        }
        $matrizData[$k][$r['fecha']] = $r;
    }
    uasort($tmpEmps, function($a,$b){ return strcmp($a['nombre'],$b['nombre']); });
    $matrizEmps = array_values($tmpEmps);

    $rutsSem = array();
    foreach ($matrizEmps as $e) $rutsSem[] = $e['rut'];
    
    $whereAS = "DATE_FORMAT(fecha,'%Y-%m') = ?";
    $paramsAS = array($mes);
    if (!empty($rutsSem)) {
        $ph2 = implode(',', array_fill(0, count($rutsSem), '?'));
        $whereAS .= " AND rut_base NOT IN ($ph2)";
        $paramsAS = array_merge($paramsAS, $rutsSem);
    }
    if ($f_dpto !== '') { $whereAS .= " AND dpto = ?"; $paramsAS[] = $f_dpto; }
    if ($f_q !== '') {
        $whereAS .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)";
        $paramsAS[] = "%$f_q%"; $paramsAS[] = "%$f_q%"; $paramsAS[] = "%$f_q%";
    }
    if ($f_estado !== '') { $whereAS .= " AND 1=0"; }

    $stmtAS = $pdo->prepare("
        SELECT DISTINCT rut_base, nombre, dpto, numero
        FROM marcaciones_resumen WHERE $whereAS ORDER BY nombre
    ");
    $stmtAS->execute($paramsAS);
    $ausentesSemana = $stmtAS->fetchAll(PDO::FETCH_ASSOC);
}

/* ─── date display ───────────────────────────────────────── */
$fechaDisplay = $DIAS[(int)$selDate->format('N')].', '.
                $selDate->format('j').' de '.
                $MESES[(int)$selDate->format('n')].' '.
                $selDate->format('Y');

/* ─── response object ────────────────────────────────────── */
$data = array(
    'mes'            => $mes,
    'mesLabel'       => $mesLabel,
    'mesPrev'        => $mesPrev,
    'mesNext'        => $mesNext,
    'hoy'            => $hoy,
    'fechaSel'       => $fechaSel,
    'modo'           => $modo,
    'fechaDisplay'   => $fechaDisplay,
    'primerDOW'      => $primerDOW,
    'diasEnMes'      => $diasEnMes,
    'dots'           => $dots,
    'stats'          => $st,
    'presentes'      => $presentes,
    'ausentes'       => $ausentes,
    'semana'         => $semana,
    'matrizEmps'     => $matrizEmps,
    'matrizData'     => $matrizData,
    'ausentesSemana' => $ausentesSemana,
    'filtros'        => array('dpto' => $f_dpto, 'estado' => $f_estado, 'q' => $f_q),
    'dptos'          => $dptosDisponibles
);

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    echo json_encode($data);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Marcaciones — Calendario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset & tokens ──────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:   #f1f5f9;
  --s1:   #ffffff;
  --s2:   #f8fafc;
  --s3:   #e2e8f0;
  --hov:  #cbd5e1;
  --b0:   #e2e8f0;
  --b1:   #cbd5e1;
  --b2:   #94a3b8;
  --t1:   #0f172a;
  --t2:   #475569;
  --t3:   #64748b;
  --blue: #2563eb;
  --blg:  rgba(37,99,235,.15);
  --bls:  rgba(37,99,235,.08);
  --grn:  #059669;
  --gng:  rgba(5,150,105,.12);
  --amb:  #d97706;
  --amg:  rgba(217,119,6,.12);
  --red:  #dc2626;
  --rdg:  rgba(220,38,38,.12);
  --sky:  #0284c7;
  --skg:  rgba(2,132,199,.12);
  --r:    12px;
  --r2:   8px;
  --font: 'Figtree', system-ui, sans-serif;
  --mono: 'JetBrains Mono', 'Courier New', monospace;
}
html{font-size:14px;-webkit-tap-highlight-color:transparent}
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--t1);
  height: 100vh;
  overflow: hidden;
  line-height: 1.45;
  display: flex; /* Convierte el body en un contenedor flexible */
  flex-direction: column; /* Apila el navbar y el contenido verticalmente */
}
a{color:inherit;text-decoration:none}
button{cursor:pointer;font-family:var(--font)}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--b2);border-radius:99px}

/* ── App shell ───────────────────────────────────────────── */
.app {
  width: 100%;
  max-width: 1920px;
  margin: 0 auto;
  padding: 20px 2%;
  flex: 1; /* Hace que .app ocupe exactamente el espacio sobrante después del navbar */
  min-height: 0; /* Previene desbordamientos en flexbox */
  display: flex;
  flex-direction: column;
}
/* ── Header ──────────────────────────────────────────────── */
.hdr{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap; flex-shrink: 0}
.mnav{display:flex;align-items:center;gap:6px}
.mnav h1{
  font-size:20px;font-weight:700;letter-spacing:-.3px;
  min-width:172px;text-align:center;white-space:nowrap;color:var(--t1);
}
.ib{
  display:flex;align-items:center;justify-content:center;
  width:32px;height:32px;border-radius:var(--r2);
  background:var(--s1);border:1px solid var(--b0);color:var(--t2);
  font-size:18px;transition:.15s;outline:none;box-shadow:0 1px 2px rgba(0,0,0,.05);
}
.ib:hover{background:var(--s3);color:var(--t1);border-color:var(--b1)}
.pill{
  padding:6px 14px;border-radius:var(--r2);
  background:var(--s1);border:1px solid var(--b0);box-shadow:0 1px 2px rgba(0,0,0,.05);
  color:var(--t2);font-size:13px;font-weight:600;transition:.15s;outline:none;
}
.pill:hover{background:var(--s3);color:var(--t1);border-color:var(--b1)}
.seg{
  display:flex;background:var(--s1);border:1px solid var(--b0);
  border-radius:var(--r2);overflow:hidden;margin-left:auto;box-shadow:0 1px 2px rgba(0,0,0,.05);
}
.seg button{
  padding:7px 16px;border:none;background:none;
  font-size:13px;font-weight:600;color:var(--t2);transition:.15s;
}
.seg button.on{background:var(--blue);color:#fff}
.seg button:hover:not(.on){background:var(--s3);color:var(--t1)}

/* ── 2-col grid ──────────────────────────────────────────── */
.grid {
    display: grid;
    grid-template-columns: 340px minmax(0,1fr);
    gap: 20px;
    align-items: stretch;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
@media(max-width:900px){
  .grid{grid-template-columns:1fr}
  .app{padding:16px 4% 50px}
}

/* ── Calendar card ───────────────────────────────────────── */
.cc {
    background: var(--s1);
    border: 1px solid var(--b0);
    border-radius: var(--r);
    padding: 12px 14px; /* Padding ligeramente reducido */
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.05), 0 2px 4px -2px rgba(0,0,0,.05);
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.cg {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-template-rows: max-content; /* Fila 1 (Cabeceras) se ajusta al texto */
    grid-auto-rows: minmax(0, 1fr); /* Las demás filas comparten todo el espacio sobrante por igual */
    gap: 4px;
    flex: 1; /* Permite absorber o encogerse según el alto disponible */
    min-height: 0; /* Previene el desbordamiento permitiendo compresión infinita */
}
.cdh {
  text-align:center;padding:5px 2px;font-size:11px;
  font-weight:700;color:var(--t3);letter-spacing:.05em;text-transform:uppercase;
}
.cdh.wk{opacity:.6}
.cd {
  position:relative;border-radius:8px;
  padding:4px 2px; /* Padding reducido para modo compacto */
  display:flex;flex-direction:column;
  align-items:center; justify-content:center; /* Centramos el contenido verticalmente */
  gap:2px;cursor:pointer;transition:.15s;border:1px solid transparent;
  min-height: 0; /* Eliminado el min-height fijo para que no fuerce empujar el layout */
}
.cd:hover{background:var(--s2);border-color:var(--b1)}
.cd.empty{cursor:default;pointer-events:none;opacity:0}
.cd.wk{background:var(--s2); opacity:.8}
.cd.wk:hover{opacity:1; border-color:var(--b1)}
.cd.hoy .dn::after{
  content:'';display:block;width:4px;height:4px;border-radius:50%;
  background:var(--blue);margin:3px auto 0;
}
.cd.sel{
  background:var(--blue)!important;opacity:1!important;
  border-color:var(--blue)!important;
  box-shadow:0 4px 12px rgba(37,99,235,.3);
}
.cd.sel .dn,.cd.sel .dc{color:#fff!important}
.cd.sel.hoy .dn::after{background:rgba(255,255,255,.8)}
.dn{font-size:13px;font-weight:700;color:var(--t1);line-height:1;font-family:var(--mono)}
.dr{display:flex;gap:3px;justify-content:center}
.dot{width:5px;height:5px;border-radius:50%}
.dok{background:var(--grn)}
.dis{background:var(--amb)}
.dc{font-size:10px;font-weight:600;color:var(--t2);font-family:var(--mono);line-height:1}

/* ── Stats row ───────────────────────────────────────────── */
.sr{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-top:10px;flex-shrink:0}
.sc{background:var(--bg);border:1px solid var(--b0);border-radius:var(--r2);padding:6px 4px;text-align:center;}
.sv{font-size:15px;font-weight:700;font-family:var(--mono);line-height:1;color:var(--t1)}
.sl{font-size:9px;font-weight:500;color:var(--t2);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ── Legend ──────────────────────────────────────────────── */
.lgnd {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--b0);
}
.li{display:flex;align-items:center;gap:4px;font-size:10px;font-weight:500;color:var(--t2)}
.lb{width:7px;height:7px;border-radius:50%;flex-shrink:0}

/* ── Right column & Filters ──────────────────────────────── */
.right {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
}
.filters-bar {
    display: flex; gap: 10px; flex-wrap: wrap; flex-shrink: 0;
}
.filters-bar input, .filters-bar select {
    padding: 8px 12px; border: 1px solid var(--b0); border-radius: var(--r2);
    font-family: var(--font); font-size: 13px; color: var(--t1); background: var(--s1);
    outline: none; transition: .15s; flex: 1; min-width: 150px;
}
.filters-bar input:focus, .filters-bar select:focus {
    border-color: var(--blue); box-shadow: 0 0 0 3px var(--blg);
}

/* ── Week strip ──────────────────────────────────────────── */
.ws {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    flex-shrink: 0;
}
.wd {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 10px 4px; cursor: pointer;
    background: var(--s1); border: 1px solid var(--b0);
    border-radius: var(--r); transition: .15s; box-shadow: 0 1px 2px rgba(0,0,0,.03);
}
.wd:hover { background: var(--s2); border-color: var(--b1); transform: translateY(-1px); }
.wd.out { opacity: .4; pointer-events: none; }
.wd.fin { background: var(--s2); }
.wd.sel {
    background: var(--blue) !important;
    border-color: var(--blue) !important;
    box-shadow: 0 4px 12px rgba(37,99,235,.25) !important;
    transform: translateY(-2px);
}
.wd.sel .wl, .wd.sel .wn, .wd.sel .wt { color: #fff !important; background: transparent !important; }
.wd.sel .wdot { background: #fff !important; }
.wd.hoy .wn { color: var(--blue); }
.wd.sel.hoy .wn { color: #fff; }
.wl { font-size: 11px; font-weight: 700; color: var(--t3); letter-spacing: .05em; text-transform: uppercase; }
.wn { font-size: 18px; font-weight: 700; font-family: var(--mono); color: var(--t1); line-height: 1; }
.wdot { width: 5px; height: 5px; border-radius: 50%; background: var(--grn); }
.wdot.hi { background: var(--amb); }
.wt { font-size: 10px; font-weight: 600; color: var(--t2); font-family: var(--mono); }

/* ── Data card ───────────────────────────────────────────── */
.dcard {
    background: var(--s1);
    border: 1px solid var(--b0);
    border-radius: var(--r);
    padding: 20px;
    transition: opacity .15s;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.05), 0 2px 4px -2px rgba(0,0,0,.05);
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dcard.loading{opacity:.5;pointer-events:none}
.dh{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;flex-shrink:0}
.tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--b0);flex-shrink:0}
.dcard-body{flex:1;min-height:0;overflow-y:auto;}
.dh h2{font-size:16px;font-weight:700;color:var(--t1);letter-spacing:-.2px}
.chips{display:flex;gap:6px;flex-wrap:wrap;margin-left:auto}
.chip{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;font-family:var(--mono);white-space:nowrap}
.chg{background:var(--gng);color:var(--grn)}
.chr{background:var(--rdg);color:var(--red)}
.chb{background:var(--skg);color:var(--sky)}
.cha{background:var(--amg);color:var(--amb)}
.tab{
  padding:8px 16px;border:none;background:none;
  font-family:var(--font);font-size:13px;font-weight:600;
  color:var(--t2);cursor:pointer;
  border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s;
}
.tab:hover{color:var(--t1);background:var(--s2);border-radius:6px 6px 0 0}
.tab.on{color:var(--blue);border-bottom-color:var(--blue)}

/* ── Table ───────────────────────────────────────────────── */
.tw{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:700px}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--b0);vertical-align:middle}
th{font-size:11px;font-weight:700;color:var(--t3);letter-spacing:.05em;text-transform:uppercase;background:var(--s2);white-space:nowrap}
td{font-size:14px}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--s2)}
.tn{font-weight:600;color:var(--t1)}
.td2{color:var(--t2);font-size:13px}
.tm{font-family:var(--mono);font-size:13px;color:var(--t1);white-space:nowrap}
.tt{font-family:var(--mono);font-size:13px;font-weight:700;color:var(--amb)}
.tc{text-align:center}
.to{font-size:12px;color:var(--t2);max-width:220px;line-height:1.4}
.edot{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--amb);vertical-align:middle;margin-left:5px}

/* ── Badges ──────────────────────────────────────────────── */
.badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.03em;white-space:nowrap}
.bok {background:var(--gng);color:var(--grn)}
.bobs{background:var(--skg);color:var(--sky)}
.binc{background:var(--amg);color:var(--amb)}
.berr{background:var(--rdg);color:var(--red)}
.be{display:inline-block;padding:5px 12px;border-radius:var(--r2);background:var(--s1);border:1px solid var(--b1);color:var(--t2);font-size:12px;font-weight:600;transition:.15s}
.be:hover{background:var(--blg);color:var(--blue);border-color:var(--blue)}

/* ── Ausentes grid ───────────────────────────────────────── */
.aug{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px}
.auc{background:var(--s1);border:1px solid var(--b0);border-left:3px solid var(--red);border-radius:var(--r2);padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.02)}
.aun{font-weight:600;font-size:14px;color:var(--t1)}
.aum{font-size:12px;color:var(--t2);margin-top:4px}

/* ── Week matrix ─────────────────────────────────────────── */
.mw{overflow-x:auto}
.mt{width:100%;border-collapse:collapse;min-width:600px}
.mt th,.mt td{padding:8px 6px;border-bottom:1px solid var(--b0);vertical-align:middle}
.mt th{font-size:11px;font-weight:700;color:var(--t3);letter-spacing:.05em;text-transform:uppercase;background:var(--s2);text-align:center}
.mt th:first-child{text-align:left;min-width:180px}
.mt tr:hover td{background:var(--s2)}
.mt tr:last-child td{border-bottom:none}
.thd{cursor:pointer;border-radius:6px;padding:4px 6px;display:inline-flex;flex-direction:column;align-items:center;gap:2px;transition:.15s}
.thd:hover{background:var(--s3)}
.thd.wk{opacity:.6}
.thd.hoy .thn{color:var(--blue)}
.thl{font-size:10px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.05em}
.thn{font-size:14px;font-weight:700;font-family:var(--mono);color:var(--t1)}
.men{font-weight:600;font-size:14px;color:var(--t1)}
.med{font-size:12px;color:var(--t2);margin-top:2px}
.mcl{text-align:center;border-radius:6px}
.mcl a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 4px;border-radius:6px;transition:.15s}
.mcl a:hover{background:var(--s1);box-shadow:0 2px 4px rgba(0,0,0,.05)}
.mok{background:var(--gng)}
.mob{background:var(--skg)}
.mic{background:var(--amg)}
.mer{background:var(--rdg)}
.mok a .ct{color:var(--grn)}
.mob a .ct{color:var(--sky)}
.mic a .ct{color:var(--amb)}
.mer a .ct{color:var(--red)}
.ct{font-family:var(--mono);font-size:11px;font-weight:600;line-height:1;white-space:nowrap}
.cs{font-size:9px;color:var(--t3)}
.ctot{font-size:10px;font-family:var(--mono);color:var(--t2);margin-top:2px}
.mem .ct{color:var(--t3);font-size:16px}

/* ── Ausentes semana ─────────────────────────────────────── */
.ass{margin-top:20px;padding-top:16px;border-top:1px solid var(--b0)}
.asttl{font-size:12px;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
.asg{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
.asi{background:var(--s1);border:1px solid var(--b0);border-left:3px solid var(--red);border-radius:var(--r2);padding:10px 12px;box-shadow:0 1px 3px rgba(0,0,0,.02)}
.asi .n{font-weight:600;font-size:13px;color:var(--t1)}
.asi .m{font-size:11px;color:var(--t2);margin-top:2px}
.empty{text-align:center;padding:48px 20px;color:var(--t3);font-size:14px;font-weight:500}

/* ── Spinner ─────────────────────────────────────────────── */
@keyframes spin{to{transform:rotate(360deg)}}
.sp{display:inline-block;width:16px;height:16px;border:3px solid var(--b1);border-top-color:var(--blue);border-radius:50%;animation:spin .65s linear infinite}

/* ── Toggle Switch (Ausencias) ───────────────────────────── */
.tgl-btn {
  display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;
  padding:6px 12px; background:var(--s1); border:1px solid var(--b0); border-radius:var(--r2);
  color:var(--t2); transition:.2s; user-select:none; height:36px; margin:0;
}
.tgl-btn:hover { background:var(--s2); border-color:var(--b1); }
.tgl-btn.active { background:var(--rdg); border-color:var(--red); color:var(--red); }
.tgl-box {
  width:32px; height:18px; background:var(--b2); border-radius:10px; position:relative; transition:.2s;
}
.tgl-box::after {
  content:''; position:absolute; top:2px; left:2px; width:14px; height:14px;
  background:#fff; border-radius:50%; transition:.2s;
}
.tgl-btn.active .tgl-box { background:var(--red); }
.tgl-btn.active .tgl-box::after { transform:translateX(14px); }

/* ── Efecto Hover para días ausentes ─────────────────────── */
.td-absent {
  position: relative;
  overflow: hidden;
}
.td-absent .normal-content {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100%;
  width: 100%;
  transition: opacity 0.2s;
}
/* Agregamos !important y flex-direction:row para evitar conflictos con .mcl a */
.td-absent a.hover-btn {
  position: absolute;
  top: 0; left: 0; width: 100%; height: 100%;
  display: flex !important; 
  flex-direction: row !important; 
  align-items: center; 
  justify-content: center; 
  gap: 4px;
  background: var(--blue) !important; 
  color: #ffffff !important; 
  text-decoration: none;
  opacity: 0; 
  transition: opacity 0.2s, background 0.2s; 
  font-size: 11px; 
  font-weight: 700;
  border-radius: 6px;
}
.td-absent:hover .normal-content { opacity: 0; }
.td-absent:hover a.hover-btn { opacity: 1; }
/* Efecto extra cuando pasas el mouse justo sobre el botón (azul más oscuro) */
.td-absent a.hover-btn:hover {
  background: #1d4ed8 !important; 
}

/* ── Mobile ──────────────────────────────────────────────── */
@media(max-width:480px){
  .mnav h1{min-width:140px;font-size:18px}
  .cd{min-height:46px;padding:6px 2px 4px}
  .dn{font-size:12px}
  .aug{grid-template-columns:1fr}
  .asg{grid-template-columns:1fr}
  .ws{gap:4px}
  .wn{font-size:15px}
}
@media(max-width: 900px) {
    body { height: auto; overflow: visible; display: block; }
    .app { height: auto; display: block; padding: 16px 4% 50px; flex: none; }
    .grid { grid-template-columns: 1fr; display: block; }
    .cc { height: auto; margin-bottom: 20px; overflow-y: visible; }
    .right { height: auto; display: block; }
    .dcard { overflow: visible; }
}
</style>
</head>
<body>
  <?php include 'navbar.php'; ?>
<div class="app" id="app">

  <div class="hdr">
    <div class="mnav">
      <button class="ib" id="btn-prev">&#8249;</button>
      <h1 id="month-title"></h1>
      <button class="ib" id="btn-next">&#8250;</button>
    </div>
    <button class="pill" id="btn-today">Hoy</button>
    <div class="seg">
      <button id="btn-dia"    data-modo="dia">Día</button>
      <button id="btn-semana" data-modo="semana">Semana</button>
    </div>
  </div>

  <div class="grid">

    <div class="cc">
      <div class="cg" id="cal"></div>
      <div class="sr" id="stats"></div>
      <div class="lgnd">
        <div class="li"><span class="lb" style="background:var(--grn)"></span>OK</div>
        <div class="li"><span class="lb" style="background:var(--amb)"></span>Incidencias</div>
        <div class="li"><span class="lb" style="background:var(--sky)"></span>Observado</div>
        <div class="li"><span class="lb" style="background:var(--red)"></span>Error</div>
      </div>
    </div>

    <div class="right">
      
      <div class="filters-bar">
        <input type="text" id="f-q" placeholder="Buscar por nombre, número o RUT..." autocomplete="off">
        <select id="f-dpto">
          <option value="">Todos los departamentos</option>
        </select>
        <select id="f-estado">
          <option value="">Todos los estados</option>
          <option value="OK">OK</option>
          <option value="OBSERVADO">Observado</option>
          <option value="INCOMPLETO">Incompleto</option>
          <option value="ERROR">Error</option>
        </select>
        
        <label class="tgl-btn" id="lbl-ausencias">
          <input type="checkbox" id="f-ausencias" style="display:none;">
          <div class="tgl-box"></div>
          <span>Ver solo inasistencias</span>
        </label>
        
        <button id="btn-exp-sem" style="padding:6px 12px;background:var(--grn);color:#fff;border:none;border-radius:var(--r2);font-weight:600;font-size:13px;transition:.15s;display:flex;align-items:center;gap:6px;height:36px;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
          Inasistencias Semanal
        </button>
        <button id="btn-exp-mes" style="padding:6px 12px;background:var(--blue);color:#fff;border:none;border-radius:var(--r2);font-weight:600;font-size:13px;transition:.15s;display:flex;align-items:center;gap:6px;height:36px;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
          Inasistencias Mensual
        </button>
      </div>

      <div class="ws" id="week-strip"></div>
      <div class="dcard" id="dcard">
        <div class="empty"><span class="sp"></span></div>
      </div>
    </div>

  </div>
</div>

<script>
/* ── Seed & state ─────────────────────────────────────────── */
var D0 = <?php echo json_encode($data); ?>;
var S  = { 
    mes: D0.mes, 
    fecha: D0.fechaSel, 
    modo: D0.modo,
    dpto: D0.filtros.dpto,
    estado: D0.filtros.estado,
    q: D0.filtros.q,
    ausencias: false
};
var cur = null;

/* ── DOM ──────────────────────────────────────────────────── */
var elTitle  = document.getElementById('month-title');
var elCal    = document.getElementById('cal');
var elStats  = document.getElementById('stats');
var elWeek   = document.getElementById('week-strip');
var elCard   = document.getElementById('dcard');
var elBtnD   = document.getElementById('btn-dia');
var elBtnSem = document.getElementById('btn-semana');
var elDpto   = document.getElementById('f-dpto');
var elEstado = document.getElementById('f-estado');
var elQ      = document.getElementById('f-q');

/* ── Utils ────────────────────────────────────────────────── */
function esc(s){
  return String(s==null?'':s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function t5(s){ return (s && String(s).length>=5) ? String(s).substring(0,5) : (s||'—') }
function badgeCls(e){ return e==='OK'?'bok':e==='OBSERVADO'?'bobs':e==='INCOMPLETO'?'binc':'berr' }
function cellCls(e){  return e==='OK'?'mok':e==='OBSERVADO'?'mob':e==='INCOMPLETO'?'mic':'mer'  }

// Actualizada para incorporar los parámetros de los filtros
function url(mes, fecha, modo, dpto, estado, q){ 
    var u = '?mes='+mes+'&fecha='+fecha+'&modo='+modo;
    if (dpto) u += '&dpto=' + encodeURIComponent(dpto);
    if (estado) u += '&estado=' + encodeURIComponent(estado);
    if (q) u += '&q=' + encodeURIComponent(q);
    return u; 
}
function loading(on){ elCard.classList.toggle('loading',on) }

/* ── Render: header y filtros ─────────────────────────────── */
function renderHdr(d){
  elTitle.textContent = d.mesLabel;
  elBtnD.classList.toggle('on',   d.modo==='dia');
  elBtnSem.classList.toggle('on', d.modo==='semana');
  
  // Poblar opciones de departamento solo si están vacías
  if(elDpto.options.length <= 1) {
      d.dptos.forEach(function(dp){
          var opt = document.createElement('option');
          opt.value = dp; opt.textContent = dp;
          elDpto.appendChild(opt);
      });
  }
  
  // Asignar valores a los inputs para reflejar el estado actual
  elDpto.value = d.filtros.dpto;
  elEstado.value = d.filtros.estado;
  if(document.activeElement !== elQ) { // Para no interrumpir si el usuario está tipeando
      elQ.value = d.filtros.q;
  }

  // Lógica visual del Toggle de ausencias
  var elToggle = document.getElementById('lbl-ausencias');
  var elToggleInput = document.getElementById('f-ausencias');
  elToggleInput.checked = S.ausencias;
  if(S.ausencias) elToggle.classList.add('active');
  else elToggle.classList.remove('active');
}

/* ── Render: calendar ─────────────────────────────────────── */
function renderCal(d){
  var h='', labels=['Lu','Ma','Mi','Ju','Vi','Sá','Do'];
  labels.forEach(function(l,i){ h+='<div class="cdh'+(i>=5?' wk':'')+'">'+l+'</div>' });
  for(var i=1;i<d.primerDOW;i++) h+='<div class="cd empty"></div>';
  var yy=+d.mes.split('-')[0], mm=+d.mes.split('-')[1];
  for(var day=1;day<=d.diasEnMes;day++){
    var pad = day<10?'0'+day:''+day;
    var f   = d.mes+'-'+pad;
    var dt  = new Date(yy,mm-1,day);
    var dow = dt.getDay(); var dowM = dow===0?7:dow;
    var wk  = dowM>=6, sel=f===d.fechaSel, hoy=f===d.hoy;
    var dot = d.dots[f]||null;
    var cls = 'cd'+(wk?' wk':'')+(sel?' sel':'')+(hoy?' hoy':'');
    h+='<div class="'+cls+'" data-fecha="'+f+'">';
    h+='<span class="dn">'+day+'</span>';
    if(dot){
      h+='<div class="dr">';
      if(+dot.ok_cnt>0)    h+='<span class="dot dok"></span>';
      if(+dot.issue_cnt>0) h+='<span class="dot dis"></span>';
      h+='</div><span class="dc">'+dot.total+'</span>';
    }
    h+='</div>';
  }
  elCal.innerHTML=h;
}

/* ── Render: stats ────────────────────────────────────────── */
function renderStats(d){
  var s=d.stats;
  elStats.innerHTML=
    '<div class="sc"><div class="sv">'+(s.empleados||0)+'</div><div class="sl">Empleados</div></div>'+
    '<div class="sc"><div class="sv">'+(s.dias_datos||0)+'</div><div class="sl">Días c/ datos</div></div>'+
    '<div class="sc"><div class="sv" style="color:var(--grn)">'+(s.ok_total||0)+'</div><div class="sl">OK</div></div>'+
    '<div class="sc"><div class="sv" style="color:var(--amb)">'+(s.inc_total||0)+'</div><div class="sl">Incidencias</div></div>'+
    '<div class="sc"><div class="sv" style="color:var(--sky)">'+(s.obs_total||0)+'</div><div class="sl">Observados</div></div>'+
    '<div class="sc"><div class="sv" style="color:var(--red)">'+(s.err_total||0)+'</div><div class="sl">Errores</div></div>';
}

/* ── Render: week strip ───────────────────────────────────── */
function renderWeek(d){
  // Si estamos en modo semana, ocultamos la barra y detenemos el renderizado
  if(d.modo === 'semana'){
    elWeek.style.display = 'none';
    return;
  }
  
  // Si estamos en modo día, nos aseguramos de que sea visible (usa grid según tu CSS)
  elWeek.style.display = 'grid';

  var h='';
  d.semana.forEach(function(w){
    var cls='wd'+(w.fin?' fin':'')+(w.fecha===d.fechaSel?' sel':'')+(w.hoy?' hoy':'')+(w.en_mes?'':' out');
    h+='<div class="'+cls+'" data-fecha="'+w.fecha+'">';
    h+='<span class="wl">'+w.label+'</span><span class="wn">'+w.num+'</span>';
    if(w.dot){
      h+='<span class="wdot'+(+w.dot.issue_cnt>0?' hi':'')+'"></span>';
      h+='<span class="wt">'+w.dot.total+'</span>';
    }
    h+='</div>';
  });
  elWeek.innerHTML=h;
}

/* ── Render: day view ─────────────────────────────────────── */
function renderDay(d){
  var p = d.presentes, a = d.ausentes, h = '';
  
  // NUEVO: Lógica para aplicar el filtro de inasistencias en el día
  var tabActiva = 'tp';
  if (S.ausencias) {
      p = []; // Vaciamos los presentes si solo se quieren ver inasistencias
      tabActiva = 'ta'; // Activamos la pestaña de ausentes por defecto
  }

  h += '<div class="dh"><h2>' + esc(d.fechaDisplay) + '</h2>';
  h += '<div class="chips"><span class="chip chg">' + d.presentes.length + ' presentes</span>';
  h += '<span class="chip chr">' + a.length + ' ausentes</span></div></div>';
  
  h += '<div class="tabs">';
  h += '<button class="tab ' + (tabActiva === 'tp' ? 'on' : '') + '" data-tab="tp">Presentes (' + p.length + ')</button>';
  h += '<button class="tab ' + (tabActiva === 'ta' ? 'on' : '') + '" data-tab="ta">Ausentes (' + a.length + ')</button>';
  h += '</div>';
  h += '<div class="dcard-body">';

  // Presentes
  h += '<div id="tp" style="' + (tabActiva === 'tp' ? '' : 'display:none') + '">';
  if(!p.length){
    h += '<div class="empty">Sin marcaciones para este día o restringido por el filtro.</div>';
  } else {
    h += '<div class="tw"><table><thead><tr>';
    h += '<th>Nombre</th><th>Dpto.</th><th class="tc">Marcas</th>';
    h += '<th>Entrada</th><th>Salida</th><th>Total</th>';
    h += '<th>Estado</th><th>Obs.</th><th></th></tr></thead><tbody>';
    p.forEach(function(r){
      h += '<tr>';
      h += '<td class="tn">' + esc(r.nombre) + (+r.editado_manual ? '<span class="edot" title="Editado manualmente"></span>' : '') + '</td>';
      h += '<td class="td2">' + esc(r.dpto) + '</td>';
      h += '<td class="tc tm">' + r.cantidad_marcaciones + '</td>';
      h += '<td class="tm">' + t5(r.entrada) + '</td>';
      h += '<td class="tm">' + t5(r.salida) + '</td>';
      h += '<td class="tt">' + t5(r.total_horas) + '</td>';
      h += '<td><span class="badge ' + badgeCls(r.estado) + '">' + esc(r.estado) + '</span></td>';
      h += '<td class="to">' + esc(r.observacion || '') + '</td>';
      h += '<td><a href="editar_marcacion_resumen.php?id=' + r.id + '" class="be">Editar</a></td>';
      h += '</tr>';
    });
    h += '</tbody></table></div>';
  }
  h += '</div>';

  // Ausentes
  h += '<div id="ta" style="' + (tabActiva === 'ta' ? '' : 'display:none') + '">';
  if(!a.length){
    h += '<div class="empty">' + (d.presentes.length ? 'Sin ausencias detectadas o restringido por filtro.' : 'Sin datos para comparar.') + '</div>';
  } else {
    h += '<div class="aug">';
    a.forEach(function(r){
      h += '<div class="auc"><div class="aun">' + esc(r.nombre) + '</div><div class="aum">' + esc(r.dpto) + ' · ' + esc(r.numero) + '</div></div>';
    });
    h += '</div>';
  }
  h += '</div>';

  h += '</div>';
  elCard.innerHTML = h;
  bindTabs();
}

/* ── Render: week matrix ──────────────────────────────────── */
function renderSemana(d){
  var mdata=d.matrizData, aus=d.ausentesSemana, sem=d.semana, h='';
  
  var emps = d.matrizEmps;
  if(S.ausencias){
      emps = emps.filter(function(emp){
          var tieneFalta = false;
          for(var i=0; i<5; i++){
              var w = sem[i];
              if(!w) continue;
              
              // ELIMINA O COMENTA LA SIGUIENTE LÍNEA:
              // if(w.fecha > d.hoy) continue; 
              
              if(!mdata[emp.rut] || !mdata[emp.rut][w.fecha]){
                  tieneFalta = true; 
                  break;
              }
          }
          return tieneFalta;
      });
  }

  h+='<div class="dh"><h2>Vista semanal</h2>';
  h+='<div class="chips"><span class="chip chg">'+emps.length+' con marcaciones</span>';
  if(aus.length) h+='<span class="chip chr">'+aus.length+' sin marcaciones (Toda la sem.)</span>';
  h+='</div></div>';

  if(!emps.length && !aus.length){
    h+='<div class="empty">Sin registros que coincidan con los filtros.</div>';
  } else {
    
    // 1. AGREGA ESTA LÍNEA PARA ABRIR EL CONTENEDOR CON SCROLL
    h+='<div class="dcard-body">'; 
    
    h+='<div class="mw"><table class="mt"><thead><tr><th>Empleado</th>';
    sem.forEach(function(w){
      var cls='thd'+(w.fin?' wk':'')+(w.hoy?' hoy':'');
      h+='<th><div class="'+cls+'" data-fecha="'+w.fecha+'">'+
           '<span class="thl">'+w.label+'</span>'+
           '<span class="thn">'+w.num+'</span></div></th>';
    });
    h+='</tr></thead><tbody>';
    
    emps.forEach(function(emp){
      var rd=mdata[emp.rut]||{};
      h+='<tr><td><div class="men">'+esc(emp.nombre)+'</div><div class="med">'+esc(emp.dpto)+'</div></td>';
      sem.forEach(function(w, i){
        // ... (se mantiene igual tu lógica de celdas)
        var c=rd[w.fecha]||null;
        if(c){
          var cc=cellCls(c.estado);
          // 1. Le agregamos la clase td-absent a la celda
          h+='<td class="mcl '+cc+' td-absent">';
          
          // 2. Le agregamos la clase normal-content al enlace con las horas
          h+='<a class="normal-content" data-fecha="'+w.fecha+'" data-modo="dia" title="Ver detalle del día" style="cursor:pointer; text-decoration:none;">';
          h+='<span class="ct">'+t5(c.entrada)+'</span>';
          h+='<span class="cs">→</span>';
          h+='<span class="ct">'+t5(c.salida)+'</span>';
          if(c.total_horas) h+='<span class="ctot">'+t5(c.total_horas)+'</span>';
          h+='</a>';

          // 3. Agregamos el botón flotante de Editar (que envía el c.id del registro existente)
          var editUrl = 'editar_marcacion_resumen.php?id=' + c.id;
          h+='<a href="'+editUrl+'" class="hover-btn" title="Editar marcación">';
          h+='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>';
          h+='Editar</a>';

          h+='</td>';
        } else {
          // URL para crear marcación enviando rut y fecha
          var editUrl = 'editar_marcacion_resumen.php?rut=' + emp.rut + '&fecha=' + w.fecha;
          
          if(i < 5 && S.ausencias){
             h+='<td class="mcl mer td-absent" style="background:var(--rdg);">';
             // Lo volvimos un <a> con data-fecha y data-modo para que el sistema reconozca el clic
             h+='<a class="normal-content" data-fecha="'+w.fecha+'" data-modo="dia" style="cursor:pointer; text-decoration:none;">';
             h+='<span class="ct" style="color:var(--red);">FALTÓ</span></a>';
          } else {
             h+='<td class="mcl mem td-absent">';
             // Igual aquí, lo volvemos un <a>
             h+='<a class="normal-content" data-fecha="'+w.fecha+'" data-modo="dia" style="cursor:pointer; text-decoration:none;">';
             h+='<span class="ct">—</span></a>';
          }
          
          // Botón oculto que aparece con el hover
          h+='<a href="'+editUrl+'" class="hover-btn" title="Agregar marcación">';
          h+='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>';
          h+='Editar</a>';
          h+='</td>';
        }
      });
      h+='</tr>';
    });
    h+='</tbody></table></div>';

    if(aus.length){
      h+='<div class="ass"><div class="asttl">Faltaron toda la semana ('+aus.length+')</div>';
      h+='<div class="asg">';
      aus.forEach(function(r){
        h+='<div class="asi"><div class="n">'+esc(r.nombre)+'</div><div class="m">'+esc(r.dpto)+' · '+esc(r.numero)+'</div></div>';
      });
      h+='</div></div>';
    }
    
    // 2. AGREGA ESTA LÍNEA PARA CERRAR EL CONTENEDOR ANTES DE ASIGNAR EL HTML
    h+='</div>'; 
    
  }
  elCard.innerHTML=h;
}

/* ── Tabs ─────────────────────────────────────────────────── */
function bindTabs(){
  var tabs=elCard.querySelectorAll('.tab');
  tabs.forEach(function(t){
    t.addEventListener('click',function(){
      tabs.forEach(function(x){ x.classList.remove('on') });
      var tp=document.getElementById('tp'), ta=document.getElementById('ta');
      if(tp) tp.style.display='none';
      if(ta) ta.style.display='none';
      t.classList.add('on');
      var el=document.getElementById(t.dataset.tab);
      if(el) el.style.display='';
    });
  });
}

/* ── Render all ───────────────────────────────────────────── */
function renderAll(d){
  cur=d;
  renderHdr(d);
  renderCal(d);
  renderStats(d);
  renderWeek(d);
  if(d.modo==='semana') renderSemana(d);
  else                  renderDay(d);
}

/* ── Navigate (fetch + no reload) ────────────────────────── */
// La función usa el estado global 'S' para armar la URL.
function navigate(push){
  var u = url(S.mes, S.fecha, S.modo, S.dpto, S.estado, S.q);
  if(push!==false) history.pushState(S, '', u);
  
  loading(true);
  fetch(u+'&json=1',{credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(d){ loading(false); renderAll(d); })
    .catch(function(){ loading(false); });
}

window.addEventListener('popstate',function(e){
  if(e.state){
      S = e.state;
      navigate(false);
  }
});

/* ── Event delegation (Botones) ───────────────────────────── */
document.getElementById('app').addEventListener('click',function(e){
  // Calendar day
  var cd=e.target.closest('.cd:not(.empty)');
  if(cd){ S.fecha = cd.dataset.fecha; navigate(); return; }

  // Week strip day
  var wd=e.target.closest('.wd:not(.out)');
  if(wd){ S.fecha = wd.dataset.fecha; navigate(); return; }

  // Matrix header day → switch to day view
  var thd=e.target.closest('.thd[data-fecha]');
  if(thd){ S.fecha = thd.dataset.fecha; S.modo = 'dia'; navigate(); return; }

  // Matrix cell link
  var ml=e.target.closest('.mcl a[data-fecha]');
  if(ml){ e.preventDefault(); S.fecha = ml.dataset.fecha; S.modo = 'dia'; navigate(); return; }

  // Prev month
  if(e.target.closest('#btn-prev')){
    if(cur) { S.mes = cur.mesPrev; S.fecha = cur.mesPrev+'-01'; navigate(); } return;
  }
  // Next month
  if(e.target.closest('#btn-next')){
    if(cur) { S.mes = cur.mesNext; S.fecha = cur.mesNext+'-01'; navigate(); } return;
  }
  // Today
  if(e.target.closest('#btn-today')){
    S.mes = D0.hoy.substring(0,7); S.fecha = D0.hoy; S.modo = 'dia'; navigate(); return;
  }
  // Mode buttons
  var mb=e.target.closest('[data-modo]');
  if(mb){ S.modo = mb.dataset.modo; navigate(); return; }
});

/* ── Listeners de los Filtros ─────────────────────────────── */
// Select de Departamento
elDpto.addEventListener('change', function(e){
    S.dpto = e.target.value;
    navigate();
});

// Select de Estado
elEstado.addEventListener('change', function(e){
    S.estado = e.target.value;
    navigate();
});

// Listener para el Checkbox de Ausencias
document.getElementById('f-ausencias').addEventListener('change', function(e){
    S.ausencias = e.target.checked;
    renderAll(cur); // Refresca la vista usando los datos en memoria
});

// Listeners para los botones de Exportar
document.getElementById('btn-exp-sem').addEventListener('click', function(){
    window.location.href = 'exportar_inasistencias.php?rango=semana&mes=' + S.mes + '&fecha=' + S.fecha;
});
document.getElementById('btn-exp-mes').addEventListener('click', function(){
    window.location.href = 'exportar_inasistencias.php?rango=mes&mes=' + S.mes + '&fecha=' + S.fecha;
});

// Input de Búsqueda (con un pequeño debounce para no saturar al escribir)
var typingTimer;
elQ.addEventListener('input', function(e){
    clearTimeout(typingTimer);
    S.q = e.target.value;
    typingTimer = setTimeout(function(){
        navigate();
    }, 400); // 400ms después de que deje de escribir, recarga.
});

/* ── Init ─────────────────────────────────────────────────── */
renderAll(D0);
history.replaceState(S, '', url(S.mes, S.fecha, S.modo, S.dpto, S.estado, S.q));
</script>
</body>
</html>