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

/* ─── month meta (Optimizado para Rangos SQL) ────────────── */
$mesDate   = new DateTime($mes.'-01');
$tmpPrev   = clone $mesDate; $mesPrev = $tmpPrev->modify('-1 month')->format('Y-m');
$tmpNext   = clone $mesDate; $mesNext = $tmpNext->modify('+1 month')->format('Y-m');
$mesLabel  = $MESES[(int)$mesDate->format('n')].' '.$mesDate->format('Y');
$diasEnMes = (int)$mesDate->format('t');
$primerDOW = (int)$mesDate->format('N');

// Para optimizar consultas DB: usar rangos en vez de DATE_FORMAT
$mesInicioSQL = $mes.'-01';
$mesFinSQL    = clone $mesDate; 
$mesFinSQL    = $mesFinSQL->modify('+1 month')->format('Y-m-01');

/* ─── Días hábiles del mes (Lunes a Viernes) ─────────────── */
$diasHabilesDelMes = 0;
$tmpDH = clone $mesDate;
for ($i = 0; $i < $diasEnMes; $i++) {
    if ((int)$tmpDH->format('N') <= 5) $diasHabilesDelMes++;
    $tmpDH->modify('+1 day');
}

/* ─── Departamentos disponibles ──────────────────────────── */
$stmtDptos = $pdo->query("SELECT DISTINCT dpto FROM marcaciones_resumen WHERE dpto != '' ORDER BY dpto");
$dptosDisponibles = $stmtDptos->fetchAll(PDO::FETCH_COLUMN);

/* ─── calendar dots ──────────────────────────────────────── */
$whereD = "fecha >= :m_start AND fecha < :m_end";
$paramsD = array(':m_start' => $mesInicioSQL, ':m_end' => $mesFinSQL);
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
    $whereSt = "fecha >= ? AND fecha < ?";
    $paramsSt = array($mesInicioSQL, $mesFinSQL);
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
$sqlA = "SELECT DISTINCT rut_base,nombre,dpto,numero FROM marcaciones_resumen 
         WHERE fecha >= :m_start AND fecha < :m_end 
           AND rut_base IS NOT NULL
           AND rut_base NOT IN (
               SELECT rut_base FROM marcaciones_resumen 
               WHERE fecha = :f AND rut_base IS NOT NULL
           )";
$paramsA = array(':m_start' => $mesInicioSQL, ':m_end' => $mesFinSQL, ':f' => $fechaSel);
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
$numSemana = (int)$lunes->format('W');

/* ─── week matrix ────────────────────────────────────────── */
$matrizEmps = array(); $matrizData = array(); $ausentesSemana = array();
if ($modo === 'semana') {
    $fechasSem = array();
    foreach ($semana as $s) $fechasSem[] = $s['fecha'];
    $ph = implode(',', array_fill(0, 7, '?'));

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

    $stmtRutsSem = $pdo->prepare("SELECT DISTINCT rut_base FROM marcaciones_resumen WHERE fecha IN ($ph) AND rut_base IS NOT NULL");
    $stmtRutsSem->execute($fechasSem);
    $todosRutsSem = array_values(array_filter(
        $stmtRutsSem->fetchAll(PDO::FETCH_COLUMN),
        function($rut){ return $rut !== null && $rut !== ''; }
    ));

    $whereAS = "fecha >= ? AND fecha < ?"; $paramsAS = array($mesInicioSQL, $mesFinSQL);
    if (!empty($todosRutsSem)) {
        $ph2 = implode(',', array_fill(0, count($todosRutsSem), '?'));
        $whereAS .= " AND rut_base NOT IN ($ph2)";
        $paramsAS = array_merge($paramsAS, $todosRutsSem);
    }
    if ($f_dpto !== '') { $whereAS .= " AND dpto = ?"; $paramsAS[] = $f_dpto; }
    if ($f_q !== '') { $whereAS .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)"; $paramsAS[] = "%$f_q%"; $paramsAS[] = "%$f_q%"; $paramsAS[] = "%$f_q%"; }
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
    $LETRAS_DIA = array('L','M','X','J','V','S', 'D');
    $tmpDateML  = clone $mesDate;
    for ($i = 0; $i < $diasEnMes; $i++) {
        $dow = (int)$tmpDateML->format('N');
        if ($dow <= 7) {
            $diasHabilesListaMes[] = array(
                'fecha'   => $tmpDateML->format('Y-m-d'),
                'label'   => $LETRAS_DIA[$dow - 1],
                'num'     => (int)$tmpDateML->format('j'),
                'hoy'     => $tmpDateML->format('Y-m-d') === $hoy,
                'esHabil' => ($dow <= 5),
            );
        }
        $tmpDateML->modify('+1 day');
    }

    $whereMatM  = "fecha >= :mes_matm_start AND fecha < :mes_matm_end";
    $paramsMatM = array(':mes_matm_start' => $mesInicioSQL, ':mes_matm_end' => $mesFinSQL);
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

    $stmtRutsMes = $pdo->prepare("SELECT DISTINCT rut_base FROM marcaciones_resumen WHERE fecha >= ? AND fecha < ? AND rut_base IS NOT NULL");
    $stmtRutsMes->execute([$mesInicioSQL, $mesFinSQL]);
    $todosRutsConMes = array_values(array_filter(
        $stmtRutsMes->fetchAll(PDO::FETCH_COLUMN),
        function($rut){ return $rut !== null && $rut !== ''; }
    ));

    if (empty($todosRutsConMes)) {
        $ausentesMes = [];
    } else {
        $phAus      = implode(',', array_fill(0, count($todosRutsConMes), '?'));
        $whereAusM  = "rut_base IS NOT NULL AND rut_base NOT IN ($phAus)";
        $paramsAusM = $todosRutsConMes;

        if ($f_dpto !== '') { $whereAusM  .= " AND dpto = ?"; $paramsAusM[] = $f_dpto; }
        if ($f_q !== '') {
            $whereAusM  .= " AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)";
            $paramsAusM[] = "%$f_q%"; $paramsAusM[] = "%$f_q%"; $paramsAusM[] = "%$f_q%";
        }
        $stmtAusM = $pdo->prepare("SELECT DISTINCT rut_base, nombre, dpto, numero FROM marcaciones_resumen WHERE $whereAusM ORDER BY nombre");
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
<link rel="stylesheet" href="static/css/calendario.css">
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
        Descargar
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
    ausencias: false,
    pagina: 1 /* NUEVO: Estado de paginación para el modo Mes */
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
        note.textContent = 'Se exportará un archivo con los empleados que faltaron al menos un día hábil dentro de este rango.';
        _exportUrl = 'exportar_inasistencias.php?rango=semana&mes='+S.mes+'&fecha='+S.fecha;

    } else {
        var mesPartes = S.mes.split('-');
        var nombreMes = MESES_FULL[parseInt(mesPartes[1])-1];

        var primerDia = S.mes+'-01';
        var lastDay   = new Date(parseInt(mesPartes[0]), parseInt(mesPartes[1]), 0);
        var ultimoDia = S.mes+'-'+(lastDay.getDate()<10?'0':'')+lastDay.getDate();

        icon.className = 'modal-icon blue';
        title.textContent = 'Descargar inasistencias del mes';
        rlabel.textContent = nombreMes + ' ' + mesPartes[0];
        rval.textContent = formatFechaLarga(primerDia) + '  →  ' + formatFechaLarga(ultimoDia);
        note.textContent = 'Se exportará un archivo con los empleados que faltaron al menos un día hábil (lun–vie) durante este mes.';
        _exportUrl = 'exportar_inasistencias.php?rango=mes&mes='+S.mes+'&fecha='+S.fecha;
    }
    modal.classList.add('open');
}

function closeExportModal(){
    document.getElementById('export-modal').classList.remove('open');
    _exportUrl = '';
}

document.getElementById('modal-confirm').addEventListener('click', function(){
    var urlParaDescargar = _exportUrl;
    closeExportModal();
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

    var elToggle=document.getElementById('lbl-ausencias');
    var elToggleInput=document.getElementById('f-ausencias');
    elToggleInput.checked=S.ausencias;
    elToggle.classList.toggle('active', S.ausencias);

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
    if(d.modo !== 'dia'){ elWeek.style.display='none'; return; }
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

    // Nuevo encabezado con KPIs estilo 'Mes'
    h += '<div class="mes-summary-hdr" style="flex-shrink:0; justify-content: flex-start; gap: 20px; padding-bottom: 16px;">';
    h += '<div style="display:flex; flex-direction:column; gap:8px;">';
    h += '<h2>'+esc(d.fechaDisplay)+'</h2>';
    h += '</div>';

    h += '<div class="mes-kpis" style="margin-left: auto;">';
    h += '<div class="mes-kpi"><div class="kv">'+(d.presentes.length + a.length)+'</div><div class="kl">Total Empleados</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--grn)">'+d.presentes.length+'</div><div class="kl">Asistencias</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--red)">'+a.length+'</div><div class="kl">Faltas</div></div>';
    h += '</div></div>';

    h+='<div class="tabs">';
    h+='<button class="tab '+(tabActiva==='tp'?'on':'')+'" data-tab="tp">Presentes ('+p.length+')</button>';
    h+='<button class="tab '+(tabActiva==='ta'?'on':'')+'" data-tab="ta">Ausentes ('+a.length+')</button>';
    h+='</div><div class="dcard-body">';

    h+='<div id="tp" style="'+(tabActiva==='tp'?'':'display:none')+'">';
    if(!p.length){
        h+='<div class="empty">Sin marcaciones para este día o restringido por el filtro.</div>';
    } else {
        h+='<div class="tw"><table class="simple-table"><thead><tr><th>Nombre</th><th>Dpto.</th><th class="tc">Marcas</th><th>Entrada</th><th>Salida</th><th>Total</th><th>Estado</th><th>Obs.</th><th></th></tr></thead><tbody>';
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

    // Calcular Asistencias y Faltas reales para la semana
    var totalPresencias = 0;
    var totalFaltas = 0;
    emps.forEach(function(emp){
        sem.forEach(function(w){
            if(w.fecha > d.hoy) return; // No sumar los días futuros
            var c = mdata[emp.rut] && mdata[emp.rut][w.fecha];
            if(c){ 
                totalPresencias++; 
            } else if(!w.fin){ 
                totalFaltas++; // Solo contamos faltas de Lunes a Viernes (!w.fin)
            }
        });
    });

    // Nuevo encabezado con KPIs estilo 'Mes'
    h += '<div class="mes-summary-hdr" style="flex-shrink:0; justify-content: flex-start; gap: 20px; padding-bottom: 16px;">';
    h += '<div style="display:flex; flex-direction:column; gap:8px;">';
    h += '<h2>Semana '+d.numSemana+' · '+esc(d.mesLabel)+'</h2>';
    h += '</div>';

    h += '<div class="mes-kpis" style="margin-left: auto;">';
    h += '<div class="mes-kpi"><div class="kv">'+emps.length+'</div><div class="kl">Empleados listados</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--grn)">'+totalPresencias+'</div><div class="kl">Asistencias</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--red)">'+totalFaltas+'</div><div class="kl">Faltas (Lun-Vie)</div></div>';
    if(aus.length) h += '<div class="mes-kpi"><div class="kv" style="color:var(--t3)">'+aus.length+'</div><div class="kl">Inactivos</div></div>';
    h += '</div></div>';

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
        
        var iconEdit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Editar';

        emps.forEach(function(emp){
            var rd=mdata[emp.rut]||{};
            h+='<tr>';
            h+='<td><div class="men">'+esc(emp.nombre)+'</div><div class="med">'+esc(emp.dpto)+'</div></td>';
            sem.forEach(function(w,i){
                var c=rd[w.fecha]||null;
                if(c){
                    var cc=cellCls(c.estado);
                    var eu='editar_marcacion_resumen.php?id='+c.id;
                    h+='<td class="mcl '+cc+' td-interactive">';
                    h+='<div class="normal-content" data-fecha="'+w.fecha+'">';
                    h+='<div><span class="ct">'+t5(c.entrada)+'</span><span class="cs" style="margin:0 4px;">→</span><span class="ct">'+t5(c.salida)+'</span></div>';
                    if(c.total_horas) h+='<span class="ctot">'+t5(c.total_horas)+'</span>';
                    h+='</div>';
                    h+='<a class="hover-overlay" href="'+eu+'">'+iconEdit+'</a>';
                    h+='</td>';
                } else {
                    var editUrl='editar_marcacion_resumen.php?rut='+emp.rut+'&fecha='+w.fecha;
                    if(i<5&&S.ausencias){
                        h+='<td class="mcl mer td-interactive" style="background:var(--rdg);">';
                        h+='<div class="normal-content" data-fecha="'+w.fecha+'"><span class="ct" style="color:var(--red);">FALTÓ</span></div>';
                    } else {
                        h+='<td class="mcl mem td-interactive">';
                        h+='<div class="normal-content" data-fecha="'+w.fecha+'"><span class="ct">—</span></div>';
                    }
                    h+='<a class="hover-overlay" href="'+editUrl+'">'+iconEdit+'</a></td>';
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

/* ── Render: mes completo con PAGINACIÓN ──────────────────── */
function renderMes(d){
    var dias  = d.diasHabilesListaMes || [];
    var mdata = d.matrizDataMes  || {};
    var aus   = d.ausentesMes    || [];
    var dh    = d.diasHabilesDelMes || 0;
    var h     = '';

    var emps = d.matrizEmpsMes || [];

    var semanas = [];
    dias.forEach(function(dia){
        var dt = new Date(dia.fecha+'T12:00:00');
        var d4 = new Date(dt.getTime()); d4.setUTCDate(d4.getUTCDate() + 4 - (d4.getUTCDay()||7));
        var ys = new Date(Date.UTC(d4.getUTCFullYear(),0,1));
        var wk = Math.ceil((((d4-ys)/86400000)+1)/7);
        
        var lastSem = semanas[semanas.length-1];
        if(!lastSem || lastSem.num !== wk){ semanas.push({ num: wk, dias: [] }); }
        semanas[semanas.length-1].dias.push(dia);
    });

    // Filtro de ausencias (lun-vie)
    if(S.ausencias){
        emps = emps.filter(function(emp){
            for(var i=0; i<dias.length; i++){
                var dia = dias[i];
                if(dia.fecha > d.hoy) continue;
                if(!dia.esHabil) continue;
                if(!mdata[emp.rut] || !mdata[emp.rut][dia.fecha]) return true;
            }
            return false;
        });
    }

    var totalPresencias=0, totalFaltas=0;
    emps.forEach(function(emp){
        dias.forEach(function(dia){
            if(dia.fecha > d.hoy) return;
            var c = mdata[emp.rut] && mdata[emp.rut][dia.fecha];
            if(c){ totalPresencias++; } 
            else if(dia.esHabil){ totalFaltas++; }
        });
    });

    /* === LÓGICA DE PAGINACIÓN === */
    var limit = 25; // Mostrar 25 empleados por página
    var totalEmps = emps.length;
    var totalPages = Math.ceil(totalEmps / limit) || 1;
    
    // Asegurar que la página no se pase de los límites
    if (S.pagina > totalPages) S.pagina = totalPages;
    if (S.pagina < 1) S.pagina = 1;

    // Extraer el subconjunto de empleados para renderizar
    var startIndex = (S.pagina - 1) * limit;
    var empsPagina = emps.slice(startIndex, startIndex + limit);

    // Cabecera con Controles de Paginación
    h += '<div class="mes-summary-hdr" style="flex-shrink:0; justify-content: flex-start; gap: 20px;">';
    
    h += '<div style="display:flex; flex-direction:column; gap:8px;">';
    h += '<h2>'+esc(d.mesLabel)+' <span style="font-weight:400;color:var(--t3);font-size:13px;">· '+dh+' días hábiles</span></h2>';
    
    // Controles generados dinámicamente
    if (totalPages > 1) {
        h += '<div style="display:flex; align-items:center; gap:10px; background:var(--s1); padding:4px 8px; border-radius:var(--r2); border:1px solid var(--b0); width: max-content;">';
        h += '<button class="ib" id="btn-prev-page" style="width:26px;height:26px;font-size:14px;" '+(S.pagina===1?'disabled':'')+'>&#8249;</button>';
        h += '<span style="font-size:12px; font-weight:600; color:var(--t2);">Pág '+S.pagina+' de '+totalPages+'</span>';
        h += '<button class="ib" id="btn-next-page" data-total="'+totalPages+'" style="width:26px;height:26px;font-size:14px;" '+(S.pagina===totalPages?'disabled':'')+'>&#8250;</button>';
        h += '</div>';
    }
    h += '</div>';

    // KPIs alineados a la derecha
    h += '<div class="mes-kpis" style="margin-left: auto;">';
    h += '<div class="mes-kpi"><div class="kv">'+totalEmps+'</div><div class="kl">Empleados listados</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--grn)">'+totalPresencias+'</div><div class="kl">Asistencias</div></div>';
    h += '<div class="mes-kpi"><div class="kv" style="color:var(--red)">'+totalFaltas+'</div><div class="kl">Faltas (Lun-Vie)</div></div>';
    if(aus.length) h += '<div class="mes-kpi"><div class="kv" style="color:var(--t3)">'+aus.length+'</div><div class="kl">Inactivos</div></div>';
    h += '</div></div>';

    if(!totalEmps && !aus.length){
        elCard.innerHTML = h + '<div class="empty">No hay empleados que presenten inasistencias de Lunes a Viernes este mes.</div>';
        return;
    }

    h += '<div class="dcard-body" style="padding:0;">';
    h += '<table class="mt" style="min-width:100%; table-layout:fixed;">';
    h += '<thead><tr>';
    h += '<th style="text-align:left; width:200px; border-right:1px solid var(--b0);">Empleado</th>';
    var diasNombres = ['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES','SÁBADO','DOMINGO'];
    for(var i=0; i<7; i++){
        h += '<th style="text-align:center; border-right:1px solid var(--b0); font-size:10px;">'+diasNombres[i]+'</th>';
    }
    h += '</tr></thead><tbody>';

    var iconEdit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Editar';

    // Ahora iteramos sobre empsPagina en lugar de emps
    empsPagina.forEach(function(emp){
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

            for(var i=1; i<=7; i++){
                var dia = dowMap[i];
                if(dia){
                    var c = rd[dia.fecha] || null;
                    var editUrl = c ? 'editar_marcacion_resumen.php?id='+c.id : 'editar_marcacion_resumen.php?rut='+emp.rut+'&fecha='+dia.fecha;
                    var esDiaHabil = dia.esHabil;

                    var tdClass = 'mcl td-interactive ';
                    if(c) tdClass += cellCls(c.estado);
                    else if(S.ausencias && esDiaHabil) tdClass += 'mer';
                    else tdClass += 'mem';

                    var bgStyle = '';
                    if(!c && S.ausencias && esDiaHabil) bgStyle = 'background:var(--rdg);';
                    else if(dia.hoy) bgStyle = 'background:var(--blg);';

                    h += '<td class="'+tdClass+'" style="'+rowBorder+' border-right:1px solid var(--b0); '+bgStyle+'">';
                    h += '<div class="normal-content" data-fecha="'+dia.fecha+'" style="position:relative;">';
                    h += '<span style="font-size:10px; font-weight:800; color:var(--t3); position:absolute; top:4px; left:6px; line-height:1;">'+dia.num+'</span>';
                    
                    if(c){
                        h += '<div style="display:flex; align-items:center; margin-top:8px;"><span class="ct">'+t5(c.entrada)+'</span><span class="cs" style="margin:0 4px;">→</span><span class="ct">'+t5(c.salida)+'</span></div>';
                        if(c.total_horas) h += '<span class="ctot" style="margin-top:0;">'+t5(c.total_horas)+'</span>';
                    } else {
                        if(S.ausencias && esDiaHabil) h += '<span class="ct" style="color:var(--red); font-size:11px; margin-top:8px;">FALTÓ</span>';
                        else h += '<span class="ct" style="margin-top:8px;">—</span>';
                    }
                    h += '</div>';
                    h += '<a class="hover-overlay" href="'+editUrl+'">'+iconEdit+'</a>';
                    h += '</td>';
                } else {
                    h += '<td style="'+rowBorder+' border-right:1px solid var(--b0); background:var(--s2); opacity:0.5;"></td>';
                }
            }
            h += '</tr>';
        });
    });

    h += '</tbody></table>';

    // Los inactivos los mostramos solo en la última página o sin paginar, para no recargar.
    // Opcional: mostrar siempre al final
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
        .then(function(d){ 
            setTimeout(function() {
                renderAll(d); 
                loading(false); 
            }, 10);
        })
        .catch(function(){ loading(false); });
}

window.addEventListener('popstate',function(e){
    if(e.state){ S=e.state; navigate(false); }
});

/* ── Event delegation ─────────────────────────────────────── */
document.getElementById('app').addEventListener('click',function(e){
    // Eventos de paginación Cliente
    if (e.target.closest('#btn-prev-page')) {
        var btn = e.target.closest('#btn-prev-page');
        if(!btn.disabled && S.pagina > 1){ 
            S.pagina--; 
            renderMes(cur); // Renderiza instantáneo sin petición al server
        } 
        return;
    }
    if (e.target.closest('#btn-next-page')) {
        var btn = e.target.closest('#btn-next-page');
        var total = parseInt(btn.dataset.total);
        if(!btn.disabled && S.pagina < total){ 
            S.pagina++; 
            renderMes(cur); // Renderiza instantáneo sin petición al server
        } 
        return;
    }

    var cd=e.target.closest('.cd:not(.empty)');
    if(cd){
        if(S.modo==='mes'){ S.fecha=cd.dataset.fecha; S.modo='dia'; S.pagina=1; navigate(); return; }
        S.fecha=cd.dataset.fecha; S.pagina=1; navigate(); return;
    }
    var wd=e.target.closest('.wd:not(.out)');
    if(wd){ S.fecha=wd.dataset.fecha; S.pagina=1; navigate(); return; }
    var thd=e.target.closest('.thd[data-fecha]');
    if(thd){ S.fecha=thd.dataset.fecha; S.modo='dia'; S.pagina=1; navigate(); return; }
    
    var cellBg=e.target.closest('.normal-content[data-fecha]');
    if(cellBg){ e.preventDefault(); S.fecha=cellBg.dataset.fecha; S.modo='dia'; S.pagina=1; navigate(); return; }
    
    if(e.target.closest('#btn-prev')){
        if(cur){ S.mes=cur.mesPrev; S.fecha=cur.mesPrev+'-01'; S.pagina=1; navigate(); } return;
    }
    if(e.target.closest('#btn-next')){
        if(cur){ S.mes=cur.mesNext; S.fecha=cur.mesNext+'-01'; S.pagina=1; navigate(); } return;
    }
    if(e.target.closest('#btn-today')){
        S.mes=D0.hoy.substring(0,7); S.fecha=D0.hoy; S.modo='dia'; S.pagina=1; navigate(); return;
    }
    var mb=e.target.closest('[data-modo]');
    if(mb){ S.modo=mb.dataset.modo; S.pagina=1; navigate(); return; }
});

/* ── Filtros ──────────────────────────────────────────────── */
elDpto.addEventListener('change',  function(e){ S.dpto=e.target.value; S.pagina=1; navigate(); });
elEstado.addEventListener('change',function(e){ S.estado=e.target.value; S.pagina=1; navigate(); });
document.getElementById('f-ausencias').addEventListener('change',function(e){
    S.ausencias=e.target.checked;
    S.pagina = 1;
    renderAll(cur); // Re-render local para aplicar filtro local
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
    S.pagina = 1;
    typingTimer=setTimeout(function(){ navigate(); },400);
});

/* ── Init ─────────────────────────────────────────────────── */
renderAll(D0);
history.replaceState(S,'',url(S.mes,S.fecha,S.modo,S.dpto,S.estado,S.q));
</script>
</body>
</html>