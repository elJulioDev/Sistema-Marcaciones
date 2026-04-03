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

$modoOpciones = ['dia', 'semana', 'mes'];
$modo = (isset($_GET['modo']) && in_array($_GET['modo'], $modoOpciones)) ? $_GET['modo'] : 'dia';

$fechaSel = (isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($_GET['fecha'])))
              ? trim($_GET['fecha'])
              : ($mes === $mesDef ? $hoy : $mes.'-01');
if (substr($fechaSel, 0, 7) !== $mes) $fechaSel = $mes.'-01';

$f_dpto   = isset($_GET['dpto'])   ? trim($_GET['dpto'])   : '';
$f_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$f_q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';

/* ─── labels ─────────────────────────────────────────────── */
$MESES  = array('','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre');
$DIAS   = array('','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo');
$DIAS_C = array('Lun','Mar','Mié','Jue','Vie','Sáb','Dom');

/* ─── month meta ─────────────────────────────────────────── */
$mesDate   = new DateTime($mes.'-01');
$tmpPrev   = clone $mesDate; $mesPrev = $tmpPrev->modify('-1 month')->format('Y-m');
$tmpNext   = clone $mesDate; $mesNext = $tmpNext->modify('+1 month')->format('Y-m');
$mesLabel  = $MESES[(int)$mesDate->format('n')].' '.$mesDate->format('Y');
$diasEnMes = (int)$mesDate->format('t');
$primerDOW = (int)$mesDate->format('N');

/* ─── Días hábiles del mes (Lunes a Viernes) ─────────────── */
$diasHabilesDelMes = 0;
$tmpDH = clone $mesDate;
for ($i = 0; $i < $diasEnMes; $i++) {
    if ((int)$tmpDH->format('N') <= 5) $diasHabilesDelMes++; // Solo Lun(1) a Vie(5)
    $tmpDH->modify('+1 day');
}

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
    $paramsD[':q1'] = "%$f_q%"; $paramsD[':q2'] = "%$f_q%"; $paramsD[':q3'] = "%$f_q%";
}
$stmtD = $pdo->prepare("
    SELECT fecha,
           COUNT(*) AS total,
           SUM(CASE WHEN estado='OK' THEN 1 ELSE 0 END) AS ok_cnt,
           SUM(CASE WHEN estado<>'OK' THEN 1 ELSE 0 END) AS issue_cnt
    FROM marcaciones_resumen WHERE $whereD GROUP BY fecha
");
$stmtD->execute($paramsD);
$dots = array();
foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $r) $dots[$r['fecha']] = $r;

/* ─── stats ──────────────────────────────────────────────── */
$paramsSt = array();
if ($modo === 'semana') {
    $selDate = new DateTime($fechaSel);
    $selDOW  = (int)$selDate->format('N');
    $lunes = clone $selDate; $lunes->modify('-'.($selDOW-1).' days');
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
if ($f_dpto !== '')  { $whereSt .= " AND dpto = ?";    $paramsSt[] = $f_dpto; }
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
$st = $stRaw ?: array('empleados'=>0,'dias_datos'=>0,'ok_total'=>0,'inc_total'=>0,'obs_total'=>0,'err_total'=>0);

/* ─── presentes (day view) ───────────────────────────────── */
$sqlP = "SELECT id,rut_base,numero,nombre,dpto,entrada,salida,total_horas,cantidad_marcaciones,estado,observacion,editado_manual FROM marcaciones_resumen WHERE fecha = :f";
$paramsP = array(':f' => $fechaSel);
if ($f_dpto !== '')  { $sqlP .= " AND dpto = :dpto";   $paramsP[':dpto']   = $f_dpto; }
if ($f_estado !== '') { $sqlP .= " AND estado = :estado"; $paramsP[':estado'] = $f_estado; }
if ($f_q !== '') {
    $sqlP .= " AND (nombre LIKE :q1 OR rut_base LIKE :q2 OR numero LIKE :q3)";
    $paramsP[':q1'] = "%$f_q%"; $paramsP[':q2'] = "%$f_q%"; $paramsP[':q3'] = "%$f_q%";
}
$sqlP .= " ORDER BY nombre ASC";
$stmtP = $pdo->prepare($sqlP); $stmtP->execute($paramsP);
$presentes = $stmtP->fetchAll(PDO::FETCH_ASSOC);

/* ─── ausentes (day view) ────────────────────────────────── */
$sqlA = "SELECT DISTINCT rut_base,nombre,dpto,numero FROM marcaciones_resumen WHERE DATE_FORMAT(fecha,'%Y-%m')=:mes AND rut_base NOT IN (SELECT rut_base FROM marcaciones_resumen WHERE fecha=:f)";
$paramsA = array(':mes' => $mes, ':f' => $fechaSel);
if ($f_dpto !== '') { $sqlA .= " AND dpto = :dpto"; $paramsA[':dpto'] = $f_dpto; }
if ($f_q !== '') {
    $sqlA .= " AND (nombre LIKE :q1 OR rut_base LIKE :q2 OR numero LIKE :q3)";
    $paramsA[':q1'] = "%$f_q%"; $paramsA[':q2'] = "%$f_q%"; $paramsA[':q3'] = "%$f_q%";
}
if ($f_estado !== '') { $sqlA .= " AND 1=0"; }
$sqlA .= " ORDER BY nombre ASC";
$stmtA = $pdo->prepare($sqlA); $stmtA->execute($paramsA);
$ausentes = $stmtA->fetchAll(PDO::FETCH_ASSOC);

/* ─── week strip ─────────────────────────────────────────── */
$selDate = new DateTime($fechaSel);
$selDOW  = (int)$selDate->format('N');
$lunes   = clone $selDate; $lunes->modify('-'.($selDOW-1).' days');
$semana  = array();
for ($i = 0; $i < 7; $i++) {
    $d = clone $lunes; $d->modify("+$i days");
    $fstr = $d->format('Y-m-d');
    $dRaw = isset($dots[$fstr]) ? $dots[$fstr] : null;
    $semana[] = array(
        'fecha'  => $fstr, 'label' => $DIAS_C[$i], 'num' => (int)$d->format('j'),
        'fin' => $i >= 5, 'hoy' => $fstr === $hoy,
        'en_mes' => substr($fstr,0,7) === $mes, 'dot' => $dRaw,
    );
}

/* ─── week number ────────────────────────────────────────── */
$numSemana = (int)$lunes->format('W');

/* ─── week matrix ────────────────────────────────────────── */
$matrizEmps = array(); $matrizData = array(); $ausentesSemana = array();
if ($modo === 'semana') {
    $fechasSem = array();
    foreach ($semana as $s) $fechasSem[] = $s['fecha'];
    $ph = implode(',', array_fill(0, 7, '?'));

    // Matriz semanal filtrada (para mostrar en la tabla con estado/dpto/q)
    $whereM = "fecha IN ($ph)"; $paramsM = $fechasSem;
    if ($f_dpto !== '')  { $whereM .= " AND dpto = ?";    $paramsM[] = $f_dpto; }
    if ($f_estado !== '') { $whereM .= " AND estado = ?"; $paramsM[] = $f_estado; }
    if ($f_q !== '') {
        $whereM .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)";
        $paramsM[] = "%$f_q%"; $paramsM[] = "%$f_q%"; $paramsM[] = "%$f_q%";
    }
    $stmtM = $pdo->prepare("SELECT id,fecha,rut_base,nombre,dpto,numero,entrada,salida,total_horas,cantidad_marcaciones,estado FROM marcaciones_resumen WHERE $whereM ORDER BY nombre ASC, fecha ASC");
    $stmtM->execute($paramsM);
    $tmpEmps = array();
    foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = $r['rut_base'];
        if (!isset($tmpEmps[$k])) $tmpEmps[$k] = array('nombre'=>$r['nombre'],'dpto'=>$r['dpto'],'numero'=>$r['numero'],'rut'=>$k);
        $matrizData[$k][$r['fecha']] = $r;
    }
    uasort($tmpEmps, function($a,$b){ return strcmp($a['nombre'],$b['nombre']); });
    $matrizEmps = array_values($tmpEmps);

    /* ── Ausentes toda la semana: independiente de filtros activos ──
     * Usamos los ruts con CUALQUIER registro en esta semana (sin filtro estado),
     * para que empleados con INCOMPLETO/ERROR no aparezcan como "ausentes toda la semana".
     */
    $stmtRutsSem = $pdo->prepare("SELECT DISTINCT rut_base FROM marcaciones_resumen WHERE fecha IN ($ph)");
    $stmtRutsSem->execute($fechasSem);
    $todosRutsSem = $stmtRutsSem->fetchAll(PDO::FETCH_COLUMN);

    $whereAS = "DATE_FORMAT(fecha,'%Y-%m') = ?"; $paramsAS = array($mes);
    if (!empty($todosRutsSem)) {
        $ph2 = implode(',', array_fill(0, count($todosRutsSem), '?'));
        $whereAS .= " AND rut_base NOT IN ($ph2)";
        $paramsAS = array_merge($paramsAS, $todosRutsSem);
    }
    if ($f_dpto !== '') { $whereAS .= " AND dpto = ?"; $paramsAS[] = $f_dpto; }
    if ($f_q !== '') { $whereAS .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)"; $paramsAS[] = "%$f_q%"; $paramsAS[] = "%$f_q%"; $paramsAS[] = "%$f_q%"; }
    // NO aplicar filtro de estado: los ausentes no tienen estado en esta semana
    $stmtAS = $pdo->prepare("SELECT DISTINCT rut_base,nombre,dpto,numero FROM marcaciones_resumen WHERE $whereAS ORDER BY nombre");
    $stmtAS->execute($paramsAS);
    $ausentesSemana = $stmtAS->fetchAll(PDO::FETCH_ASSOC);
}

/* ─── matriz mensual completa ────────────────────────────── */
$diasHabilesListaMes = array();
$matrizEmpsMes       = array();
$matrizDataMes       = array();
$ausentesMes         = array();

if ($modo === 'mes') {
    /* Lista de TODOS los días del mes (L-D para layout de tabla semanal) */
    $LETRAS_DIA = array('L','M','X','J','V','S', 'D');
    $tmpDateML  = clone $mesDate;
    for ($i = 0; $i < $diasEnMes; $i++) {
        $dow = (int)$tmpDateML->format('N');
        if ($dow <= 7) { // Todos los días (1=Lun ... 7=Dom) para el layout de 7 columnas
            $diasHabilesListaMes[] = array(
                'fecha'   => $tmpDateML->format('Y-m-d'),
                'label'   => $LETRAS_DIA[$dow - 1],
                'num'     => (int)$tmpDateML->format('j'),
                'hoy'     => $tmpDateML->format('Y-m-d') === $hoy,
                'esHabil' => ($dow <= 5), // true si es Lun-Vie
            );
        }
        $tmpDateML->modify('+1 day');
    }

    /* Registros del mes con filtros */
    $whereMatM  = "DATE_FORMAT(fecha,'%Y-%m') = :mes_matm";
    $paramsMatM = array(':mes_matm' => $mes);
    if ($f_dpto !== '')  { $whereMatM .= " AND dpto = :dpto_matm";   $paramsMatM[':dpto_matm']   = $f_dpto; }
    if ($f_estado !== '') { $whereMatM .= " AND estado = :estado_matm"; $paramsMatM[':estado_matm'] = $f_estado; }
    if ($f_q !== '') {
        $whereMatM .= " AND (nombre LIKE :q_matm1 OR rut_base LIKE :q_matm2 OR numero LIKE :q_matm3)";
        $paramsMatM[':q_matm1'] = "%$f_q%";
        $paramsMatM[':q_matm2'] = "%$f_q%";
        $paramsMatM[':q_matm3'] = "%$f_q%";
    }
    $stmtMatM = $pdo->prepare("
        SELECT id, fecha, rut_base, nombre, dpto, numero,
               entrada, salida, total_horas, cantidad_marcaciones, estado
        FROM marcaciones_resumen
        WHERE $whereMatM
        ORDER BY nombre ASC, fecha ASC
    ");
    $stmtMatM->execute($paramsMatM);

    $tmpEmpsM = array();
    foreach ($stmtMatM->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = $r['rut_base'];
        if (!isset($tmpEmpsM[$k])) {
            $tmpEmpsM[$k] = array('nombre'=>$r['nombre'],'dpto'=>$r['dpto'],'numero'=>$r['numero'],'rut'=>$k);
        }
        $matrizDataMes[$k][$r['fecha']] = $r;
    }
    uasort($tmpEmpsM, function($a,$b){ return strcmp($a['nombre'],$b['nombre']); });
    $matrizEmpsMes = array_values($tmpEmpsM);

    /* ── Empleados SIN NINGUNA marcación en el mes ────────────
     *  IMPORTANTE: Esta consulta es INDEPENDIENTE de los filtros activos
     *  (estado, dpto, q). Un empleado es "inactivo" únicamente si no tiene
     *  NINGÚN registro en este mes, sin importar el estado de esos registros.
     *  Esto evita que empleados con registros INCOMPLETO/ERROR aparezcan como
     *  inactivos cuando hay un filtro de estado=OK activo.
     * ────────────────────────────────────────────────────────── */

    // Paso 1: obtener TODOS los ruts con cualquier registro este mes (sin filtros)
    $stmtRutsMes = $pdo->prepare(
        "SELECT DISTINCT rut_base FROM marcaciones_resumen WHERE DATE_FORMAT(fecha,'%Y-%m') = ?"
    );
    $stmtRutsMes->execute([$mes]);
    $todosRutsConMes = $stmtRutsMes->fetchAll(PDO::FETCH_COLUMN);

    // Paso 2: buscar empleados que existan en la BD pero NO tengan registros este mes
    if (empty($todosRutsConMes)) {
        // Sin datos este mes → nadie tiene marcaciones, lista de inactivos vacía
        $ausentesMes = [];
    } else {
        $phAus      = implode(',', array_fill(0, count($todosRutsConMes), '?'));
        $whereAusM  = "rut_base NOT IN ($phAus)";
        $paramsAusM = $todosRutsConMes;

        // Aplicamos filtros de dpto y búsqueda libre (pero NO filtro de estado,
        // ya que estos empleados no tienen registros en el mes actual)
        if ($f_dpto !== '') {
            $whereAusM  .= " AND dpto = ?";
            $paramsAusM[] = $f_dpto;
        }
        if ($f_q !== '') {
            $whereAusM  .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)";
            $paramsAusM[] = "%$f_q%";
            $paramsAusM[] = "%$f_q%";
            $paramsAusM[] = "%$f_q%";
        }

        $stmtAusM = $pdo->prepare(
            "SELECT DISTINCT rut_base, nombre, dpto, numero
             FROM marcaciones_resumen
             WHERE $whereAusM
             ORDER BY nombre"
        );
        $stmtAusM->execute($paramsAusM);
        $ausentesMes = $stmtAusM->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* ─── date display ───────────────────────────────────────── */
$fechaDisplay = $DIAS[(int)$selDate->format('N')].', '.
                $selDate->format('j').' de '.
                $MESES[(int)$selDate->format('n')].' '.
                $selDate->format('Y');

/* ─── response object ────────────────────────────────────── */
$data = array(
    'mes'              => $mes,
    'mesLabel'         => $mesLabel,
    'mesPrev'          => $mesPrev,
    'mesNext'          => $mesNext,
    'hoy'              => $hoy,
    'fechaSel'         => $fechaSel,
    'modo'             => $modo,
    'fechaDisplay'     => $fechaDisplay,
    'primerDOW'        => $primerDOW,
    'diasEnMes'        => $diasEnMes,
    'diasHabilesDelMes'=> $diasHabilesDelMes,
    'numSemana'        => $numSemana,
    'dots'             => $dots,
    'stats'            => $st,
    'presentes'        => $presentes,
    'ausentes'         => $ausentes,
    'semana'           => $semana,
    'matrizEmps'       => $matrizEmps,
    'matrizData'       => $matrizData,
    'ausentesSemana'      => $ausentesSemana,
    'diasHabilesListaMes' => $diasHabilesListaMes,
    'matrizEmpsMes'       => $matrizEmpsMes,
    'matrizDataMes'       => $matrizDataMes,
    'ausentesMes'         => $ausentesMes,
    'filtros'          => array('dpto' => $f_dpto, 'estado' => $f_estado, 'q' => $f_q),
    'dptos'            => $dptosDisponibles
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
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f1f5f9;--s1:#ffffff;--s2:#f8fafc;--s3:#e2e8f0;--hov:#cbd5e1;
  --b0:#e2e8f0;--b1:#cbd5e1;--b2:#94a3b8;
  --t1:#0f172a;--t2:#475569;--t3:#64748b;
  --blue:#2563eb;--blg:rgba(37,99,235,.15);--bls:rgba(37,99,235,.08);
  --grn:#059669;--gng:rgba(5,150,105,.12);
  --amb:#d97706;--amg:rgba(217,119,6,.12);
  --red:#dc2626;--rdg:rgba(220,38,38,.12);
  --sky:#0284c7;--skg:rgba(2,132,199,.12);
  --r:12px;--r2:8px;
  --font:'Figtree',system-ui,sans-serif;
  --mono:'JetBrains Mono','Courier New',monospace;
}
html{font-size:14px;-webkit-tap-highlight-color:transparent}
body{font-family:var(--font);background:var(--bg);color:var(--t1);height:100vh;overflow:hidden;line-height:1.45;display:flex;flex-direction:column;}
a{color:inherit;text-decoration:none}
button{cursor:pointer;font-family:var(--font)}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--b2);border-radius:99px}

/* ── App ─────────────────────────────────────────────────── */
.app{width:100%;max-width:1920px;margin:0 auto;padding:20px 2%;flex:1;min-height:0;display:flex;flex-direction:column;}

/* ── Header ──────────────────────────────────────────────── */
.hdr{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;flex-shrink:0;}
.mnav{display:flex;align-items:center;gap:6px;}
.mnav h1{font-size:20px;font-weight:700;letter-spacing:-.3px;min-width:172px;text-align:center;white-space:nowrap;}
.ib{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:var(--r2);background:var(--s1);border:1px solid var(--b0);color:var(--t2);font-size:18px;transition:.15s;outline:none;box-shadow:0 1px 2px rgba(0,0,0,.05);}
.ib:hover{background:var(--s3);color:var(--t1);border-color:var(--b1)}
.pill{padding:6px 14px;border-radius:var(--r2);background:var(--s1);border:1px solid var(--b0);box-shadow:0 1px 2px rgba(0,0,0,.05);color:var(--t2);font-size:13px;font-weight:600;transition:.15s;outline:none;}
.pill:hover{background:var(--s3);color:var(--t1);border-color:var(--b1)}

/* ── Semana badge (header) ───────────────────────────────── */
.week-badge{
  display:none;align-items:center;gap:8px;
  padding:6px 12px;border-radius:var(--r2);
  background:var(--blg);border:1px solid rgba(37,99,235,.25);
  color:var(--blue);font-size:13px;font-weight:600;white-space:nowrap;
}
.week-badge .wbi{font-family:var(--mono);font-weight:700;font-size:14px;}
.week-badge .wbd{color:var(--t2);font-size:12px;}
.week-badge .wbd-sep{color:var(--b2);margin:0 2px;}

/* Mes badge */
.mes-badge{
  display:none;align-items:center;gap:8px;
  padding:6px 12px;border-radius:var(--r2);
  background:rgba(5,150,105,.1);border:1px solid rgba(5,150,105,.25);
  color:var(--grn);font-size:13px;font-weight:600;white-space:nowrap;
}
.mes-badge svg{flex-shrink:0;}

.seg{display:flex;background:var(--s1);border:1px solid var(--b0);border-radius:var(--r2);overflow:hidden;margin-left:auto;box-shadow:0 1px 2px rgba(0,0,0,.05);}
.seg button{padding:7px 16px;border:none;background:none;font-size:13px;font-weight:600;color:var(--t2);transition:.15s;}
.seg button.on{background:var(--blue);color:#fff}
.seg button:hover:not(.on){background:var(--s3);color:var(--t1)}

/* ── Grid ────────────────────────────────────────────────── */
.grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:20px;align-items:stretch;flex:1;min-height:0;overflow:hidden;}

/* ── Calendar card ───────────────────────────────────────── */
.cc{background:var(--s1);border:1px solid var(--b0);border-radius:var(--r);padding:12px 14px;box-shadow:0 4px 6px -1px rgba(0,0,0,.05),0 2px 4px -2px rgba(0,0,0,.05);height:100%;display:flex;flex-direction:column;overflow:hidden;}
.cg{display:grid;grid-template-columns:repeat(7,1fr);grid-template-rows:max-content;grid-auto-rows:minmax(0,1fr);gap:4px;flex:1;min-height:0;}
.cdh{text-align:center;padding:5px 2px;font-size:11px;font-weight:700;color:var(--t3);letter-spacing:.05em;text-transform:uppercase;}
.cdh.wk{opacity:.6}
.cd{position:relative;border-radius:8px;padding:4px 2px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;cursor:pointer;transition:.15s;border:1px solid transparent;min-height:0;}
.cd:hover{background:var(--s2);border-color:var(--b1)}
.cd.empty{cursor:default;pointer-events:none;opacity:0}
.cd.wk{background:var(--s2);opacity:.8}
.cd.wk:hover{opacity:1;border-color:var(--b1)}
.cd.hoy .dn::after{content:'';display:block;width:4px;height:4px;border-radius:50%;background:var(--blue);margin:3px auto 0;}
.cd.sel{background:var(--blue)!important;opacity:1!important;border-color:var(--blue)!important;box-shadow:0 4px 12px rgba(37,99,235,.3);}
.cd.sel .dn,.cd.sel .dc{color:#fff!important}
.cd.sel.hoy .dn::after{background:rgba(255,255,255,.8)}

/* ── In-week highlight ───────────────────────────────────── */
.cd.in-week:not(.sel){
  background:rgba(37,99,235,.07);
  border-color:rgba(37,99,235,.2);
}
.cd.in-week:not(.sel):hover{
  background:rgba(37,99,235,.13);
  border-color:rgba(37,99,235,.35);
}
/* Primer y último día de la semana visible */
.cd.in-week-first:not(.sel){ border-radius:8px 8px 8px 8px; }

.dn{font-size:13px;font-weight:700;color:var(--t1);line-height:1;font-family:var(--mono)}
.dr{display:flex;gap:3px;justify-content:center}
.dot{width:5px;height:5px;border-radius:50%}
.dok{background:var(--grn)}.dis{background:var(--amb)}
.dc{font-size:10px;font-weight:600;color:var(--t2);font-family:var(--mono);line-height:1}

/* ── Stats row ───────────────────────────────────────────── */
.sr{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-top:10px;flex-shrink:0;}
.sc{background:var(--bg);border:1px solid var(--b0);border-radius:var(--r2);padding:6px 4px;text-align:center;}
.sv{font-size:15px;font-weight:700;font-family:var(--mono);line-height:1;color:var(--t1)}
.sl{font-size:9px;font-weight:500;color:var(--t2);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ── Legend ──────────────────────────────────────────────── */
.lgnd{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;flex-shrink:0;margin-top:10px;padding-top:10px;border-top:1px solid var(--b0);}
.li{display:flex;align-items:center;gap:4px;font-size:10px;font-weight:500;color:var(--t2)}
.lb{width:7px;height:7px;border-radius:50%;flex-shrink:0}

/* ── Right column ────────────────────────────────────────── */
.right{display:flex;flex-direction:column;gap:16px;min-width:0;min-height:0;height:100%;overflow:hidden;}
.filters-bar{display:flex;gap:10px;flex-wrap:wrap;flex-shrink:0;}
.filters-bar input,.filters-bar select{padding:8px 12px;border:1px solid var(--b0);border-radius:var(--r2);font-family:var(--font);font-size:13px;color:var(--t1);background:var(--s1);outline:none;transition:.15s;flex:1;min-width:150px;}
.filters-bar input:focus,.filters-bar select:focus{border-color:var(--blue);box-shadow:0 0 0 3px var(--blg);}

/* ── Week strip ──────────────────────────────────────────── */
.ws{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;flex-shrink:0;}
.wd{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 4px;cursor:pointer;background:var(--s1);border:1px solid var(--b0);border-radius:var(--r);transition:.15s;box-shadow:0 1px 2px rgba(0,0,0,.03);}
.wd:hover{background:var(--s2);border-color:var(--b1);transform:translateY(-1px);}
.wd.out{opacity:.4;pointer-events:none;}
.wd.fin{background:var(--s2);}
.wd.sel{background:var(--blue)!important;border-color:var(--blue)!important;box-shadow:0 4px 12px rgba(37,99,235,.25)!important;transform:translateY(-2px);}
.wd.sel .wl,.wd.sel .wn,.wd.sel .wt{color:#fff!important;background:transparent!important;}
.wd.sel .wdot{background:#fff!important;}
.wd.hoy .wn{color:var(--blue);}
.wd.sel.hoy .wn{color:#fff;}
.wl{font-size:11px;font-weight:700;color:var(--t3);letter-spacing:.05em;text-transform:uppercase;}
.wn{font-size:18px;font-weight:700;font-family:var(--mono);color:var(--t1);line-height:1;}
.wdot{width:5px;height:5px;border-radius:50%;background:var(--grn);}
.wdot.hi{background:var(--amb);}
.wt{font-size:10px;font-weight:600;color:var(--t2);font-family:var(--mono);}

/* ── Data card ───────────────────────────────────────────── */
.dcard{background:var(--s1);border:1px solid var(--b0);border-radius:var(--r);padding:20px;transition:opacity .15s;box-shadow:0 4px 6px -1px rgba(0,0,0,.05),0 2px 4px -2px rgba(0,0,0,.05);flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden;}
.dcard.loading{opacity:.5;pointer-events:none}
.dh{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;flex-shrink:0;}
.tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--b0);flex-shrink:0;}
.dcard-body{flex:1;min-height:0;overflow-y:auto;}
.dh h2{font-size:16px;font-weight:700;color:var(--t1);letter-spacing:-.2px;}
.chips{display:flex;gap:6px;flex-wrap:wrap;margin-left:auto;}
.chip{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;font-family:var(--mono);white-space:nowrap;}
.chg{background:var(--gng);color:var(--grn)}.chr{background:var(--rdg);color:var(--red)}
.chb{background:var(--skg);color:var(--sky)}.cha{background:var(--amg);color:var(--amb)}
.tab{padding:8px 16px;border:none;background:none;font-family:var(--font);font-size:13px;font-weight:600;color:var(--t2);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s;}
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
.tn{font-weight:600;color:var(--t1)}.td2{color:var(--t2);font-size:13px}
.tm{font-family:var(--mono);font-size:13px;color:var(--t1);white-space:nowrap}
.tt{font-family:var(--mono);font-size:13px;font-weight:700;color:var(--amb)}
.tc{text-align:center}.to{font-size:12px;color:var(--t2);max-width:220px;line-height:1.4}
.edot{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--amb);vertical-align:middle;margin-left:5px}

/* ── Badges ──────────────────────────────────────────────── */
.badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.03em;white-space:nowrap}
.bok{background:var(--gng);color:var(--grn)}.bobs{background:var(--skg);color:var(--sky)}
.binc{background:var(--amg);color:var(--amb)}.berr{background:var(--rdg);color:var(--red)}
.be{display:inline-block;padding:5px 12px;border-radius:var(--r2);background:var(--s1);border:1px solid var(--b1);color:var(--t2);font-size:12px;font-weight:600;transition:.15s}
.be:hover{background:var(--blg);color:var(--blue);border-color:var(--blue)}

/* ── Ausentes grid ───────────────────────────────────────── */
.aug{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px}
.auc{background:var(--s1);border:1px solid var(--b0);border-left:3px solid var(--red);border-radius:var(--r2);padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.02)}
.aun{font-weight:600;font-size:14px;color:var(--t1)}.aum{font-size:12px;color:var(--t2);margin-top:4px}

/* ── Week matrix ─────────────────────────────────────────── */
.mw{overflow-x:auto}.mt{width:100%;border-collapse:collapse;min-width:600px}
.mt th,.mt td{padding:8px 6px;border-bottom:1px solid var(--b0);vertical-align:middle}
.mt th{font-size:11px;font-weight:700;color:var(--t3);letter-spacing:.05em;text-transform:uppercase;background:var(--s2);text-align:center}
.mt th:first-child{text-align:left;min-width:180px}
.mt tr:hover td{background:var(--s2)}.mt tr:last-child td{border-bottom:none}
.thd{cursor:pointer;border-radius:6px;padding:4px 6px;display:inline-flex;flex-direction:column;align-items:center;gap:2px;transition:.15s}
.thd:hover{background:var(--s3)}.thd.wk{opacity:.6}.thd.hoy .thn{color:var(--blue)}
.thl{font-size:10px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.05em}
.thn{font-size:14px;font-weight:700;font-family:var(--mono);color:var(--t1)}
.men{font-weight:600;font-size:14px;color:var(--t1)}.med{font-size:12px;color:var(--t2);margin-top:2px}
.mcl{text-align:center;border-radius:6px}
.mcl a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 4px;border-radius:6px;transition:.15s}
.mcl a:hover{background:var(--s1);box-shadow:0 2px 4px rgba(0,0,0,.05)}
.mok{background:var(--gng)}.mob{background:var(--skg)}.mic{background:var(--amg)}.mer{background:var(--rdg)}
.mok a .ct{color:var(--grn)}.mob a .ct{color:var(--sky)}.mic a .ct{color:var(--amb)}.mer a .ct{color:var(--red)}
.ct{font-family:var(--mono);font-size:11px;font-weight:600;line-height:1;white-space:nowrap}
.cs{font-size:9px;color:var(--t3)}.ctot{font-size:10px;font-family:var(--mono);color:var(--t2);margin-top:2px}
.mem .ct{color:var(--t3);font-size:16px}
.ass{margin-top:20px;padding-top:16px;border-top:1px solid var(--b0)}
.asttl{font-size:12px;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
.asg{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
.asi{background:var(--s1);border:1px solid var(--b0);border-left:3px solid var(--red);border-radius:var(--r2);padding:10px 12px}
.asi .n{font-weight:600;font-size:13px;color:var(--t1)}.asi .m{font-size:11px;color:var(--t2);margin-top:2px}

/* ── Mes table ───────────────────────────────────────────── */
.mes-table{width:100%;border-collapse:collapse;min-width:680px;}
.mes-table th{font-size:11px;font-weight:700;color:var(--t3);letter-spacing:.05em;text-transform:uppercase;background:var(--s2);padding:10px 12px;border-bottom:1px solid var(--b0);white-space:nowrap;}
.mes-table td{padding:10px 12px;border-bottom:1px solid var(--b0);vertical-align:middle;font-size:13px;}
.mes-table tr:last-child td{border-bottom:none;}
.mes-table tr:hover td{background:var(--s2);}
/* Barra de presencia */
.pbar-wrap{display:flex;align-items:center;gap:8px;}
.pbar{height:6px;border-radius:999px;background:var(--b0);flex:1;min-width:60px;overflow:hidden;}
.pbar-fill{height:100%;border-radius:999px;background:var(--grn);transition:width .3s;}
.pbar-fill.med-att{background:var(--amb);}
.pbar-fill.low-att{background:var(--red);}
.pbar-label{font-family:var(--mono);font-size:12px;font-weight:700;color:var(--t2);white-space:nowrap;}
/* Falta highlight */
.falta-cero{color:var(--grn);font-weight:700;}
.falta-few{color:var(--amb);font-weight:700;}
.falta-many{color:var(--red);font-weight:700;}
/* Mes summary header */
.mes-summary-hdr{
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;
  gap:10px;margin-bottom:16px;padding:14px 16px;
  background:linear-gradient(135deg,rgba(5,150,105,.08),rgba(37,99,235,.06));
  border:1px solid rgba(5,150,105,.2);border-radius:var(--r2);flex-shrink:0;
}
.mes-summary-hdr h2{font-size:15px;font-weight:700;color:var(--t1);}
.mes-kpis{display:flex;gap:8px;flex-wrap:wrap;}
.mes-kpi{display:flex;flex-direction:column;align-items:center;padding:6px 12px;background:var(--s1);border:1px solid var(--b0);border-radius:var(--r2);}
.mes-kpi .kv{font-family:var(--mono);font-size:16px;font-weight:700;color:var(--t1);}
.mes-kpi .kl{font-size:10px;font-weight:600;color:var(--t3);white-space:nowrap;margin-top:1px;}

/* ── Misc ────────────────────────────────────────────────── */
.empty{text-align:center;padding:48px 20px;color:var(--t3);font-size:14px;font-weight:500}
@keyframes spin{to{transform:rotate(360deg)}}
.sp{display:inline-block;width:16px;height:16px;border:3px solid var(--b1);border-top-color:var(--blue);border-radius:50%;animation:spin .65s linear infinite}
.tgl-btn{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;padding:6px 12px;background:var(--s1);border:1px solid var(--b0);border-radius:var(--r2);color:var(--t2);transition:.2s;user-select:none;height:36px;margin:0;}
.tgl-btn:hover{background:var(--s2);border-color:var(--b1);}
.tgl-btn.active{background:var(--rdg);border-color:var(--red);color:var(--red);}
.tgl-box{width:32px;height:18px;background:var(--b2);border-radius:10px;position:relative;transition:.2s;}
.tgl-box::after{content:'';position:absolute;top:2px;left:2px;width:14px;height:14px;background:#fff;border-radius:50%;transition:.2s;}
.tgl-btn.active .tgl-box{background:var(--red);}
.tgl-btn.active .tgl-box::after{transform:translateX(14px);}
.td-absent{position:relative;overflow:hidden;}
.td-absent .normal-content{display:flex;justify-content:center;align-items:center;height:100%;width:100%;transition:opacity .2s;}
.td-absent a.hover-btn{position:absolute;top:0;left:0;width:100%;height:100%;display:flex!important;flex-direction:row!important;align-items:center;justify-content:center;gap:4px;background:var(--blue)!important;color:#ffffff!important;text-decoration:none;opacity:0;transition:opacity .2s,background .2s;font-size:11px;font-weight:700;border-radius:6px;}
.td-absent:hover .normal-content{opacity:0;}
.td-absent:hover a.hover-btn{opacity:1;}
.td-absent a.hover-btn:hover{background:#1d4ed8!important;}

/* ── Export Modal ────────────────────────────────────────── */
.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(15,23,42,.55);
  z-index:9999;
  align-items:center;justify-content:center;
  backdrop-filter:blur(3px);
}
.modal-overlay.open{display:flex;}
.modal-box{
  background:var(--s1);border-radius:var(--r);
  padding:28px;max-width:440px;width:90%;
  box-shadow:0 24px 80px rgba(0,0,0,.22);
  animation:modalIn .2s ease;
}
@keyframes modalIn{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:none}}
.modal-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;}
.modal-icon.green{background:var(--gng);color:var(--grn);}
.modal-icon.blue{background:var(--blg);color:var(--blue);}
.modal-box h3{font-size:17px;font-weight:700;color:var(--t1);margin-bottom:8px;}
.modal-range{
  display:flex;align-items:center;gap:8px;margin:14px 0;
  padding:10px 14px;background:var(--s2);border:1px solid var(--b0);
  border-radius:var(--r2);font-size:13px;
}
.modal-range .mr-label{font-weight:600;color:var(--t3);font-size:11px;text-transform:uppercase;letter-spacing:.05em;}
.modal-range .mr-val{font-family:var(--mono);font-weight:700;color:var(--t1);}
.modal-note{font-size:13px;color:var(--t2);line-height:1.6;margin:0;}
.modal-actions{display:flex;gap:10px;margin-top:20px;}
.modal-actions button{flex:1;padding:11px;border:none;border-radius:var(--r2);font-weight:700;font-size:14px;cursor:pointer;font-family:var(--font);transition:.15s;}
.modal-btn-confirm{background:var(--grn);color:#fff;}
.modal-btn-confirm:hover{background:#047857;}
.modal-btn-cancel{background:var(--s2);color:var(--t2);border:1px solid var(--b0)!important;border:none;}
.modal-btn-cancel:hover{background:var(--s3);}

/* ── Vista Mes Ultra Compacta ── */
.mes-compact-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: max-content; }
.mes-compact-table th, .mes-compact-table td { padding: 0; border-bottom: 1px solid var(--b0); border-right: 1px solid var(--b0); vertical-align: middle; }
.mes-compact-table th { background: var(--s2); border-top: 1px solid var(--b0); }
/* Primera columna congelada (Sticky) para no perder al empleado al hacer scroll */
.mes-compact-table .sticky-col {
    position: sticky; left: 0; z-index: 5; background: var(--s1);
    border-right: 2px solid var(--b1); padding: 8px 12px; text-align: left;
    min-width: 200px;
}
.mes-compact-table thead .sticky-col { background: var(--s2); z-index: 6; border-top: none; }
/* Celda de día compacta */
.td-compact { height: 48px; min-width: 44px; position: relative; background: var(--s1); }
.td-compact:hover .hover-overlay { opacity: 1; }
.mcl-compact { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; width: 100%; text-decoration: none; padding: 2px; transition: background .15s; }
/* Textos apilados y más pequeños para optimizar espacio */
.ct-stack { font-family: var(--mono); font-size: 10px; font-weight: 700; line-height: 1.15; letter-spacing: -0.05em; color: var(--t1); }
.ctot-compact { font-family: var(--mono); font-size: 8px; font-weight: 700; color: var(--t2); margin-top: 2px; background: rgba(0,0,0,0.06); padding: 1px 4px; border-radius: 3px; }
/* Botón flotante elegante al pasar el mouse por la celda */
.hover-overlay {
    position: absolute; inset: 0; background: rgba(37,99,235,0.95); color: white;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .15s; text-decoration: none;
}
.hover-overlay svg { width: 18px; height: 18px; }
/* Colores de estado para vista compacta */
.td-compact.mok .ct-stack { color: var(--grn); }
.td-compact.mob { background: var(--skg); } .td-compact.mob .ct-stack { color: var(--sky); }
.td-compact.mic { background: var(--amg); } .td-compact.mic .ct-stack { color: var(--amb); }
.td-compact.mer { background: var(--rdg); } .td-compact.mer .ct-stack { color: var(--red); }

/* ── Mobile ──────────────────────────────────────────────── */
@media(max-width:900px){
  body{height:auto;overflow:visible;display:block;}
  .app{height:auto;display:block;padding:16px 4% 50px;flex:none;}
  .grid{grid-template-columns:1fr;display:block;}
  .cc{height:auto;margin-bottom:20px;overflow-y:visible;}
  .right{height:auto;display:block;}
  .dcard{overflow:visible;}
}
@media(max-width:480px){
  .mnav h1{min-width:140px;font-size:18px}
  .aug{grid-template-columns:1fr}.asg{grid-template-columns:1fr}
  .ws{gap:4px}.wn{font-size:15px}
  .seg button{padding:7px 10px;font-size:12px;}
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

    <!-- Badge semana / mes -->
    <div class="week-badge" id="week-badge">
      <span class="wbi" id="wbadge-num"></span>
      <span class="wbd" id="wbadge-range"></span>
    </div>
    <div class="mes-badge" id="mes-badge">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span id="mes-badge-text"></span>
    </div>

    <div class="seg">
      <button id="btn-dia"    data-modo="dia">Día</button>
      <button id="btn-semana" data-modo="semana">Semana</button>
      <button id="btn-mes"    data-modo="mes">Mes</button>
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
        <div class="li"><span class="lb" style="background:rgba(37,99,235,.25);border:1px solid rgba(37,99,235,.4)"></span>Semana sel.</div>
      </div>
    </div>

    <div class="right">
      <div class="filters-bar">
        <input type="text" id="f-q" placeholder="Buscar por nombre, número o RUT..." autocomplete="off">
        <select id="f-dpto"><option value="">Todos los departamentos</option></select>
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
          <span>Solo inasistencias</span>
        </label>
        <button id="btn-exp-sem" style="padding:6px 12px;background:var(--grn);color:#fff;border:none;border-radius:var(--r2);font-weight:600;font-size:13px;transition:.15s;display:flex;align-items:center;gap:6px;height:36px;cursor:pointer;">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Inasistencias Semanal
        </button>
        <button id="btn-exp-mes" style="padding:6px 12px;background:var(--blue);color:#fff;border:none;border-radius:var(--r2);font-weight:600;font-size:13px;transition:.15s;display:flex;align-items:center;gap:6px;height:36px;cursor:pointer;">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
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

<!-- ── Export Modal ─────────────────────────────────────── -->
<div class="modal-overlay" id="export-modal">
  <div class="modal-box">
    <div class="modal-icon" id="modal-icon">
      <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
    </div>
    <h3 id="modal-title"></h3>
    <div class="modal-range">
      <div>
        <div class="mr-label" id="modal-range-label"></div>
        <div class="mr-val" id="modal-range-val"></div>
      </div>
    </div>
    <p class="modal-note" id="modal-note"></p>
    <div class="modal-actions">
      <button class="modal-btn-confirm" id="modal-confirm">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Descargar CSV
      </button>
      <button class="modal-btn-cancel" id="modal-cancel">Cancelar</button>
    </div>
  </div>
</div>

<script>
/* ── Seed & state ─────────────────────────────────────────── */
var D0 = <?php echo json_encode($data); ?>;
var S  = {
    mes: D0.mes, fecha: D0.fechaSel, modo: D0.modo,
    dpto: D0.filtros.dpto, estado: D0.filtros.estado, q: D0.filtros.q,
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
var elBtnMes = document.getElementById('btn-mes');
var elDpto   = document.getElementById('f-dpto');
var elEstado = document.getElementById('f-estado');
var elQ      = document.getElementById('f-q');

/* ── Helpers ──────────────────────────────────────────────── */
function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function t5(s){ return (s && String(s).length>=5) ? String(s).substring(0,5) : (s||'—'); }
function badgeCls(e){ return e==='OK'?'bok':e==='OBSERVADO'?'bobs':e==='INCOMPLETO'?'binc':'berr'; }
function cellCls(e){  return e==='OK'?'mok':e==='OBSERVADO'?'mob':e==='INCOMPLETO'?'mic':'mer'; }
function loading(on){ elCard.classList.toggle('loading',on); }

var MESES_CORTOS=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
var MESES_FULL=['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function formatFechaCorta(fechaStr){
    var p=fechaStr.split('-');
    return parseInt(p[2])+' '+MESES_CORTOS[parseInt(p[1])-1];
}
function formatFechaLarga(fechaStr){
    var p=fechaStr.split('-');
    var dias=['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    var d=new Date(fechaStr+'T12:00:00');
    return dias[d.getDay()]+' '+parseInt(p[2])+' '+MESES_CORTOS[parseInt(p[1])-1]+' '+p[0];
}

function url(mes,fecha,modo,dpto,estado,q){
    var u='?mes='+mes+'&fecha='+fecha+'&modo='+modo;
    if(dpto)   u+='&dpto='+encodeURIComponent(dpto);
    if(estado) u+='&estado='+encodeURIComponent(estado);
    if(q)      u+='&q='+encodeURIComponent(q);
    return u;
}

/* ── Export Modal ─────────────────────────────────────────── */
var _exportUrl = '';

function showExportModal(type){
    var modal   = document.getElementById('export-modal');
    var icon    = document.getElementById('modal-icon');
    var title   = document.getElementById('modal-title');
    var rlabel  = document.getElementById('modal-range-label');
    var rval    = document.getElementById('modal-range-val');
    var note    = document.getElementById('modal-note');

    if(type === 'semana'){
        var sem     = cur.semana;
        var lunes   = sem[0];
        var viernes = sem[4];
        var weekNum = cur.numSemana;
        var mesL    = cur.mesLabel;

        icon.className = 'modal-icon green';
        title.textContent = 'Descargar inasistencias de la semana';
        rlabel.textContent = 'Semana ' + weekNum + ' · ' + mesL;
        rval.textContent = formatFechaLarga(lunes.fecha) + '  →  ' + formatFechaLarga(viernes.fecha);
        note.textContent = 'Se exportará un CSV con los empleados que faltaron al menos un día hábil dentro de este rango.';
        _exportUrl = 'exportar_inasistencias.php?rango=semana&mes='+S.mes+'&fecha='+S.fecha;

    } else {
        var mesPartes = S.mes.split('-');
        var nombreMes = MESES_FULL[parseInt(mesPartes[1])-1];

        // Primer y último día hábil del mes
        var primerDia = S.mes+'-01';
        var lastDay   = new Date(parseInt(mesPartes[0]), parseInt(mesPartes[1]), 0);
        var ultimoDia = S.mes+'-'+(lastDay.getDate()<10?'0':'')+lastDay.getDate();

        icon.className = 'modal-icon blue';
        title.textContent = 'Descargar inasistencias del mes';
        rlabel.textContent = nombreMes + ' ' + mesPartes[0];
        rval.textContent = formatFechaLarga(primerDia) + '  →  ' + formatFechaLarga(ultimoDia);
        note.textContent = 'Se exportará un CSV con los empleados que faltaron al menos un día hábil (lun–vie) durante este mes.';
        _exportUrl = 'exportar_inasistencias.php?rango=mes&mes='+S.mes+'&fecha='+S.fecha;
    }

    modal.classList.add('open');
}

function closeExportModal(){
    document.getElementById('export-modal').classList.remove('open');
    _exportUrl = '';
}

document.getElementById('modal-confirm').addEventListener('click', function(){
    var urlParaDescargar = _exportUrl;   // guardar primero
    closeExportModal();                  // ahora sí cierra y limpia
    if(urlParaDescargar) window.open(urlParaDescargar, '_blank');
});

document.getElementById('modal-cancel').addEventListener('click', closeExportModal);
document.getElementById('export-modal').addEventListener('click', function(e){
    if(e.target === this) closeExportModal();
});

/* ── Render: header ───────────────────────────────────────── */
function renderHdr(d){
    elTitle.textContent = d.mesLabel;
    elBtnD.classList.toggle('on',   d.modo==='dia');
    elBtnSem.classList.toggle('on', d.modo==='semana');
    elBtnMes.classList.toggle('on', d.modo==='mes');

    // Poblar departamentos
    if(elDpto.options.length <= 1){
        d.dptos.forEach(function(dp){
            var opt=document.createElement('option');
            opt.value=dp; opt.textContent=dp;
            elDpto.appendChild(opt);
        });
    }
    elDpto.value=d.filtros.dpto;
    elEstado.value=d.filtros.estado;
    if(document.activeElement !== elQ) elQ.value=d.filtros.q;

    // Toggle ausencias
    var elToggle=document.getElementById('lbl-ausencias');
    var elToggleInput=document.getElementById('f-ausencias');
    elToggleInput.checked=S.ausencias;
    elToggle.classList.toggle('active', S.ausencias);

    // Badges
    var wb    = document.getElementById('week-badge');
    var mb    = document.getElementById('mes-badge');
    var wbNum = document.getElementById('wbadge-num');
    var wbRng = document.getElementById('wbadge-range');
    var mbTxt = document.getElementById('mes-badge-text');

    if(d.modo === 'semana'){
        var sem     = d.semana;
        var lunes   = sem[0];
        var viernes = sem[4];
        wbNum.textContent  = 'Sem. '+d.numSemana;
        wbRng.textContent  = formatFechaCorta(lunes.fecha)+' → '+formatFechaCorta(viernes.fecha)+' '+lunes.fecha.split('-')[0];
        wb.style.display='flex'; mb.style.display='none';
    } else if(d.modo === 'mes'){
        mbTxt.textContent = 'Mostrando todo '+d.mesLabel;
        mb.style.display='flex'; wb.style.display='none';
    } else {
        wb.style.display='none'; mb.style.display='none';
    }
}

/* ── Render: calendar ─────────────────────────────────────── */
function renderCal(d){
    // Calcular fechas de la semana activa para highlight
    var weekDates = {};
    if(d.modo === 'semana'){
        d.semana.forEach(function(w){ weekDates[w.fecha] = true; });
    }

    var h='', labels=['Lu','Ma','Mi','Ju','Vi','Sá','Do'];
    labels.forEach(function(l,i){ h+='<div class="cdh'+(i>=5?' wk':'')+'">'+l+'</div>'; });
    for(var i=1;i<d.primerDOW;i++) h+='<div class="cd empty"></div>';
    var yy=+d.mes.split('-')[0], mm=+d.mes.split('-')[1];
    for(var day=1;day<=d.diasEnMes;day++){
        var pad = day<10?'0'+day:''+day;
        var f   = d.mes+'-'+pad;
        var dt  = new Date(yy,mm-1,day);
        var dow = dt.getDay(); var dowM = dow===0?7:dow;
        var wk  = dowM>=6;
        var sel = f===d.fechaSel;
        var hoy = f===d.hoy;
        var inW = !!weekDates[f];
        var dot = d.dots[f]||null;
        var cls = 'cd'+(wk?' wk':'')+(sel?' sel':'')+(hoy?' hoy':'')+(inW&&!sel?' in-week':'');
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

/* ── Render: week strip (día mode only) ───────────────────── */
function renderWeek(d){
    if(d.modo !== 'dia'){
        elWeek.style.display='none';
        return;
    }
    elWeek.style.display='grid';
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
    var p=d.presentes, a=d.ausentes, h='';
    var tabActiva='tp';
    if(S.ausencias){ p=[]; tabActiva='ta'; }

    h+='<div class="dh"><h2>'+esc(d.fechaDisplay)+'</h2>';
    h+='<div class="chips"><span class="chip chg">'+d.presentes.length+' presentes</span>';
    h+='<span class="chip chr">'+a.length+' ausentes</span></div></div>';
    h+='<div class="tabs">';
    h+='<button class="tab '+(tabActiva==='tp'?'on':'')+'" data-tab="tp">Presentes ('+p.length+')</button>';
    h+='<button class="tab '+(tabActiva==='ta'?'on':'')+'" data-tab="ta">Ausentes ('+a.length+')</button>';
    h+='</div><div class="dcard-body">';

    // Presentes
    h+='<div id="tp" style="'+(tabActiva==='tp'?'':'display:none')+'">';
    if(!p.length){
        h+='<div class="empty">Sin marcaciones para este día o restringido por el filtro.</div>';
    } else {
        h+='<div class="tw"><table><thead><tr><th>Nombre</th><th>Dpto.</th><th class="tc">Marcas</th><th>Entrada</th><th>Salida</th><th>Total</th><th>Estado</th><th>Obs.</th><th></th></tr></thead><tbody>';
        p.forEach(function(r){
            h+='<tr>';
            h+='<td class="tn">'+esc(r.nombre)+(+r.editado_manual?'<span class="edot" title="Editado manualmente"></span>':'')+'</td>';
            h+='<td class="td2">'+esc(r.dpto)+'</td>';
            h+='<td class="tc tm">'+r.cantidad_marcaciones+'</td>';
            h+='<td class="tm">'+t5(r.entrada)+'</td>';
            h+='<td class="tm">'+t5(r.salida)+'</td>';
            h+='<td class="tt">'+t5(r.total_horas)+'</td>';
            h+='<td><span class="badge '+badgeCls(r.estado)+'">'+esc(r.estado)+'</span></td>';
            h+='<td class="to">'+esc(r.observacion||'')+'</td>';
            h+='<td><a href="editar_marcacion_resumen.php?id='+r.id+'" class="be">Editar</a></td>';
            h+='</tr>';
        });
        h+='</tbody></table></div>';
    }
    h+='</div>';

    // Ausentes
    h+='<div id="ta" style="'+(tabActiva==='ta'?'':'display:none')+'">';
    if(!a.length){
        h+='<div class="empty">'+(d.presentes.length?'Sin ausencias detectadas o restringido por filtro.':'Sin datos para comparar.')+'</div>';
    } else {
        h+='<div class="aug">';
        a.forEach(function(r){
            h+='<div class="auc"><div class="aun">'+esc(r.nombre)+'</div><div class="aum">'+esc(r.dpto)+' · '+esc(r.numero)+'</div></div>';
        });
        h+='</div>';
    }
    h+='</div></div>';
    elCard.innerHTML=h;
    bindTabs();
}

/* ── Render: week matrix ──────────────────────────────────── */
function renderSemana(d){
    var mdata=d.matrizData, aus=d.ausentesSemana, sem=d.semana, h='';
    var emps=d.matrizEmps;
    if(S.ausencias){
        emps=emps.filter(function(emp){
            for(var i=0;i<5;i++){
                var w=sem[i]; if(!w) continue;
                if(!mdata[emp.rut]||!mdata[emp.rut][w.fecha]) return true;
            }
            return false;
        });
    }
    h+='<div class="dh"><h2>Semana '+d.numSemana+' · '+esc(d.mesLabel)+'</h2>';
    h+='<div class="chips"><span class="chip chg">'+emps.length+' con marcaciones</span>';
    if(aus.length) h+='<span class="chip chr">'+aus.length+' sin marcaciones</span>';
    h+='</div></div>';

    if(!emps.length && !aus.length){
        h+='<div class="empty">Sin registros que coincidan con los filtros.</div>';
    } else {
        h+='<div class="dcard-body">';
        h+='<div class="mw"><table class="mt"><thead><tr><th>Empleado</th>';
        sem.forEach(function(w){
            var cls='thd'+(w.fin?' wk':'')+(w.hoy?' hoy':'');
            h+='<th><div class="'+cls+'" data-fecha="'+w.fecha+'"><span class="thl">'+w.label+'</span><span class="thn">'+w.num+'</span></div></th>';
        });
        h+='</tr></thead><tbody>';
        emps.forEach(function(emp){
            var rd=mdata[emp.rut]||{};
            h+='<tr><td><div class="men">'+esc(emp.nombre)+'</div><div class="med">'+esc(emp.dpto)+'</div></td>';
            sem.forEach(function(w,i){
                var c=rd[w.fecha]||null;
                if(c){
                    var cc=cellCls(c.estado);
                    h+='<td class="mcl '+cc+' td-absent">';
                    h+='<a class="normal-content" data-fecha="'+w.fecha+'" data-modo="dia" style="cursor:pointer;text-decoration:none;">';
                    h+='<span class="ct">'+t5(c.entrada)+'</span><span class="cs">→</span><span class="ct">'+t5(c.salida)+'</span>';
                    if(c.total_horas) h+='<span class="ctot">'+t5(c.total_horas)+'</span>';
                    h+='</a>';
                    h+='<a href="editar_marcacion_resumen.php?id='+c.id+'" class="hover-btn" title="Editar marcación">';
                    h+='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
                    h+='Editar</a></td>';
                } else {
                    var editUrl='editar_marcacion_resumen.php?rut='+emp.rut+'&fecha='+w.fecha;
                    if(i<5&&S.ausencias){
                        h+='<td class="mcl mer td-absent" style="background:var(--rdg);">';
                        h+='<a class="normal-content" data-fecha="'+w.fecha+'" data-modo="dia" style="cursor:pointer;text-decoration:none;">';
                        h+='<span class="ct" style="color:var(--red);">FALTÓ</span></a>';
                    } else {
                        h+='<td class="mcl mem td-absent">';
                        h+='<a class="normal-content" data-fecha="'+w.fecha+'" data-modo="dia" style="cursor:pointer;text-decoration:none;">';
                        h+='<span class="ct">—</span></a>';
                    }
                    h+='<a href="'+editUrl+'" class="hover-btn" title="Agregar marcación">';
                    h+='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
                    h+='Editar</a></td>';
                }
            });
            h+='</tr>';
        });
        h+='</tbody></table></div>';
        if(aus.length){
            h+='<div class="ass"><div class="asttl">Faltaron toda la semana ('+aus.length+')</div><div class="asg">';
            aus.forEach(function(r){
                h+='<div class="asi"><div class="n">'+esc(r.nombre)+'</div><div class="m">'+esc(r.dpto)+' · '+esc(r.numero)+'</div></div>';
            });
            h+='</div></div>';
        }
        h+='</div>';
    }
    elCard.innerHTML=h;
}

/* ── Render: mes completo (Corregido: Inteligencia de Fines de Semana) ─────────── */
function renderMes(d){
    var dias  = d.diasHabilesListaMes || [];
    var mdata = d.matrizDataMes  || {};
    var aus   = d.ausentesMes    || [];
    var dh    = d.diasHabilesDelMes || 0;
    var h     = '';

    var emps = d.matrizEmpsMes || [];

    // 1. Agrupar los días hábiles del mes en sus respectivas semanas
    var semanas = [];
    var semActual = null;
    dias.forEach(function(dia){
        var dt = new Date(dia.fecha+'T12:00:00');
        var d4 = new Date(dt.getTime()); d4.setUTCDate(d4.getUTCDate() + 4 - (d4.getUTCDay()||7));
        var ys = new Date(Date.UTC(d4.getUTCFullYear(),0,1));
        var wk = Math.ceil((((d4-ys)/86400000)+1)/7);
        
        var lastSem = semanas[semanas.length-1];
        if(!lastSem || lastSem.num !== wk){
            semanas.push({ num: wk, dias: [] });
        }
        semanas[semanas.length-1].dias.push(dia);
    });

    // 2. Filtrar empleados con al menos 1 falta en día hábil pasado
    if(S.ausencias){
        emps = emps.filter(function(emp){
            for(var i=0; i<dias.length; i++){
                var dia = dias[i];
                if(dia.fecha > d.hoy) continue;         // Ignorar días futuros
                if(!dia.esHabil) continue;              // Ignorar Sáb y Dom
                if(!mdata[emp.rut] || !mdata[emp.rut][dia.fecha]) return true; // Tiene falta
            }
            return false;
        });
    }

    // 3. Calcular KPIs (solo días hábiles pasados = Lun-Vie hasta hoy)
    var totalPresencias=0, totalFaltas=0;
    emps.forEach(function(emp){
        dias.forEach(function(dia){
            if(dia.fecha > d.hoy) return;   // No contar días futuros
            var c = mdata[emp.rut] && mdata[emp.rut][dia.fecha];
            if(c){
                totalPresencias++;
            } else if(dia.esHabil){         // Falta solo si es Lun-Vie
                totalFaltas++;
            }
        });
    });

    h += '<div class="mes-summary-hdr" style="flex-shrink:0;">';
    h += '<h2>'+esc(d.mesLabel)+' <span style="font-weight:400;color:var(--t3);font-size:13px;">· '+dh+' días hábiles</span></h2>';
    h += '<div class="mes-kpis">';
    h += '<div class="mes-kpi"><div class="kv">'+emps.length+'</div><div class="kl">Empleados listados</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--grn)">'+totalPresencias+'</div><div class="kl">Asistencias</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--red)">'+totalFaltas+'</div><div class="kl">Faltas (Lun-Vie)</div></div>';
    if(aus.length) h += '<div class="mes-kpi"><div class="kv" style="color:var(--t3)">'+aus.length+'</div><div class="kl">Inactivos</div></div>';
    h += '</div></div>';

    if(!emps.length && !aus.length){
        elCard.innerHTML = h + '<div class="empty">No hay empleados que presenten inasistencias de Lunes a Viernes este mes.</div>';
        return;
    }

    h += '<div class="dcard-body" style="padding:0;">';
    
    // TABLA FIJA DE 8 COLUMNAS
    h += '<table class="mt" style="min-width:100%; table-layout:fixed;">';
    h += '<thead><tr>';
    h += '<th style="text-align:left; width:200px; border-right:1px solid var(--b0);">Empleado</th>';
    var diasNombres = ['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES','SÁBADO','DOMINGO'];
    for(var i=0; i<7; i++){
        h += '<th style="text-align:center; border-right:1px solid var(--b0); font-size:10px;">'+diasNombres[i]+'</th>';
    }
    h += '</tr></thead><tbody>';

    emps.forEach(function(emp){
        var rd = mdata[emp.rut] || {};
        
        semanas.forEach(function(sem, wIdx){
            var isLastWeek = (wIdx === semanas.length - 1);
            var rowBorder = isLastWeek ? 'border-bottom: 2px solid var(--b1);' : 'border-bottom: 1px dashed var(--b0);';
            
            h += '<tr>';
            
            if(wIdx === 0){
                h += '<td rowspan="'+semanas.length+'" style="vertical-align:top; border-right:1px solid var(--b0); border-bottom: 2px solid var(--b1); background:var(--s1);">';
                h += '<div class="men">'+esc(emp.nombre)+'</div>';
                h += '<div class="med">'+esc(emp.dpto)+' · '+esc(emp.numero)+'</div>';
                h += '</td>';
            }
            
            var dowMap = {};
            sem.dias.forEach(function(d_obj){
                var dt = new Date(d_obj.fecha+'T12:00:00');
                var num = dt.getDay() || 7; 
                dowMap[num] = d_obj;
            });
            
            var editIcon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';

            for(var i=1; i<=7; i++){
                var dia = dowMap[i];
                
                if(dia){
                    var c = rd[dia.fecha] || null;
                    // Eliminamos 'esFuturo' para que las celdas siempre sean editables
                    var editUrl = c ? 'editar_marcacion_resumen.php?id='+c.id : 'editar_marcacion_resumen.php?rut='+emp.rut+'&fecha='+dia.fecha;
                    
                    var esDiaHabil = dia.esHabil;

                    // Asignar clases (eliminamos el 'else if(esFuturo) tdClass += 'mem';')
                    var tdClass = 'mcl td-absent ';
                    if(c) tdClass += cellCls(c.estado);
                    else if(S.ausencias && esDiaHabil) tdClass += 'mer'; // Fondo rojo siempre si es L-V
                    else tdClass += 'mem';

                    var bgStyle = '';
                    if(!c && S.ausencias && esDiaHabil) bgStyle = 'background:var(--rdg);';
                    else if(dia.hoy) bgStyle = 'background:var(--blg);';

                    h += '<td class="'+tdClass+'" style="'+rowBorder+' border-right:1px solid var(--b0); padding:0; height:56px; '+bgStyle+'">';
                    
                    // Quitamos la opacidad restrictiva para que el texto siempre sea visible
                    h += '<a class="normal-content" data-fecha="'+dia.fecha+'" data-modo="dia" style="cursor:pointer; text-decoration:none; display:flex; flex-direction:column; justify-content:center; align-items:center; width:100%; height:100%; position:relative; gap:2px;">';
                    
                    h += '<span style="font-size:10px; font-weight:800; color:var(--t3); position:absolute; top:4px; left:6px; line-height:1;">'+dia.num+'</span>';
                    
                    if(c){
                        h += '<div style="display:flex; align-items:center; margin-top:8px;"><span class="ct">'+t5(c.entrada)+'</span><span class="cs" style="margin:0 4px;">→</span><span class="ct">'+t5(c.salida)+'</span></div>';
                        if(c.total_horas) h += '<span class="ctot" style="margin-top:0;">'+t5(c.total_horas)+'</span>';
                    } else {
                        // Forzamos "FALTÓ" visualmente de Lunes a Viernes en cualquier día del mes
                        if(S.ausencias && esDiaHabil){
                            h += '<span class="ct" style="color:var(--red); font-size:11px; margin-top:8px;">FALTÓ</span>';
                        } else {
                            h += '<span class="ct" style="margin-top:8px;">—</span>';
                        }
                    }
                    h += '</a>';

                    // Quitamos el bloque 'if(!esFuturo)' que ocultaba el botón flotante
                    h += '<a href="'+editUrl+'" class="hover-btn" title="'+(c?'Editar':'Agregar')+' marcación" style="border-radius:0;">'+editIcon+'Editar</a>';

                    h += '</td>';
                } else {
                    h += '<td style="'+rowBorder+' border-right:1px solid var(--b0); background:var(--s2); opacity:0.5;"></td>';
                }
            }
            h += '</tr>';
        });
    });

    h += '</tbody></table>';

    // Aquellos 52 registros que aparecen abajo son personas que están en la DB pero que no vinieron ni una vez en el mes.
    // Les cambié el título para que no generen confusión.
    if(aus.length){
        h += '<div class="ass" style="margin:20px 16px 16px 16px; border-top:1px solid var(--b0); padding-top:16px;">';
        h += '<div class="asttl" style="color:var(--t3);">Empleados Inactivos este mes ('+aus.length+')</div>';
        h += '<div class="asg">';
        aus.forEach(function(r){
            h += '<div class="asi" style="border-left-color:var(--b2);"><div class="n">'+esc(r.nombre)+'</div><div class="m">'+esc(r.dpto)+' · '+esc(r.numero)+'</div></div>';
        });
        h += '</div></div>';
    }

    h += '</div>';

    elCard.innerHTML = h;
}

/* ── Tabs ─────────────────────────────────────────────────── */
function bindTabs(){
    var tabs=elCard.querySelectorAll('.tab');
    tabs.forEach(function(t){
        t.addEventListener('click',function(){
            tabs.forEach(function(x){ x.classList.remove('on'); });
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
    cur = d;
    renderHdr(d);
    renderCal(d);
    renderStats(d);
    renderWeek(d);
    if(d.modo==='semana')     renderSemana(d);
    else if(d.modo==='mes')   renderMes(d);
    else                      renderDay(d);
}

/* ── Navigate ─────────────────────────────────────────────── */
function navigate(push){
    var u=url(S.mes,S.fecha,S.modo,S.dpto,S.estado,S.q);
    if(push!==false) history.pushState(S,'',u);
    loading(true);
    fetch(u+'&json=1',{credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){ loading(false); renderAll(d); })
        .catch(function(){ loading(false); });
}

window.addEventListener('popstate',function(e){
    if(e.state){ S=e.state; navigate(false); }
});

/* ── Event delegation ─────────────────────────────────────── */
document.getElementById('app').addEventListener('click',function(e){
    var cd=e.target.closest('.cd:not(.empty)');
    if(cd){
        // En modo mes, clic en día → cambiar a día mode
        if(S.modo==='mes'){ S.fecha=cd.dataset.fecha; S.modo='dia'; navigate(); return; }
        S.fecha=cd.dataset.fecha; navigate(); return;
    }
    var wd=e.target.closest('.wd:not(.out)');
    if(wd){ S.fecha=wd.dataset.fecha; navigate(); return; }
    var thd=e.target.closest('.thd[data-fecha]');
    if(thd){ S.fecha=thd.dataset.fecha; S.modo='dia'; navigate(); return; }
    var ml=e.target.closest('.mcl a[data-fecha]');
    if(ml){ e.preventDefault(); S.fecha=ml.dataset.fecha; S.modo='dia'; navigate(); return; }
    if(e.target.closest('#btn-prev')){
        if(cur){ S.mes=cur.mesPrev; S.fecha=cur.mesPrev+'-01'; navigate(); } return;
    }
    if(e.target.closest('#btn-next')){
        if(cur){ S.mes=cur.mesNext; S.fecha=cur.mesNext+'-01'; navigate(); } return;
    }
    if(e.target.closest('#btn-today')){
        S.mes=D0.hoy.substring(0,7); S.fecha=D0.hoy; S.modo='dia'; navigate(); return;
    }
    var mb=e.target.closest('[data-modo]');
    if(mb){ S.modo=mb.dataset.modo; navigate(); return; }
});

/* ── Filtros ──────────────────────────────────────────────── */
elDpto.addEventListener('change',  function(e){ S.dpto=e.target.value; navigate(); });
elEstado.addEventListener('change',function(e){ S.estado=e.target.value; navigate(); });
document.getElementById('f-ausencias').addEventListener('change',function(e){
    S.ausencias=e.target.checked;
    renderAll(cur);
});

/* ── Exportar (con modal) ─────────────────────────────────── */
document.getElementById('btn-exp-sem').addEventListener('click', function(){
    showExportModal('semana');
});
document.getElementById('btn-exp-mes').addEventListener('click', function(){
    showExportModal('mes');
});

/* ── Search debounce ─────────────────────────────────────────*/
var typingTimer;
elQ.addEventListener('input',function(e){
    clearTimeout(typingTimer);
    S.q=e.target.value;
    typingTimer=setTimeout(function(){ navigate(); },400);
});

/* ── Init ─────────────────────────────────────────────────── */
renderAll(D0);
history.replaceState(S,'',url(S.mes,S.fecha,S.modo,S.dpto,S.estado,S.q));
</script>
</body>
</html>