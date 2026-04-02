<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php'; // Protegemos el archivo
date_default_timezone_set('America/Santiago');

$pdo = db();

$rango    = isset($_GET['rango']) ? $_GET['rango'] : 'semana';
$mes      = isset($_GET['mes']) ? trim($_GET['mes']) : date('Y-m');
$fechaSel = isset($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-d');

$diasRevisar = [];
$tituloArchivo = '';

if ($rango === 'semana') {
    // Calcular Lunes a Viernes de la semana seleccionada
    $selDate = new DateTime($fechaSel);
    $selDOW  = (int)$selDate->format('N');
    $lunes = clone $selDate;
    $lunes->modify('-'.($selDOW-1).' days');

    for ($i = 0; $i < 5; $i++) { // Solo 5 días hábiles
        $d = clone $lunes;
        $d->modify("+$i days");
        $diasRevisar[] = $d->format('Y-m-d');
    }
    $tituloArchivo = 'Inasistencias_Semana_'.$lunes->format('d-m-Y').'.csv';

} else {
    // Calcular Lunes a Viernes de TODO el mes
    $inicioMes = new DateTime($mes . '-01');
    $finMes = new DateTime($mes . '-01');
    $finMes->modify('last day of this month');

    $periodo = new DatePeriod($inicioMes, new DateInterval('P1D'), $finMes->modify('+1 day'));
    
    foreach ($periodo as $dt) {
        if ($dt->format('N') <= 5) { // 1(Lun) a 5(Vie)
            $diasRevisar[] = $dt->format('Y-m-d');
        }
    }
    $tituloArchivo = 'Inasistencias_Mes_'.$mes.'.csv';
}

// 1. Obtener la lista base de empleados activos en este mes
$stmtBase = $pdo->prepare("SELECT DISTINCT rut_base, nombre, dpto, numero FROM marcaciones_resumen WHERE DATE_FORMAT(fecha,'%Y-%m') = ? ORDER BY nombre ASC");
$stmtBase->execute([$mes]);
$empleados = $stmtBase->fetchAll(PDO::FETCH_ASSOC);

// 2. Obtener marcas del rango de fechas
$ph = implode(',', array_fill(0, count($diasRevisar), '?'));
$stmtM = $pdo->prepare("SELECT fecha, rut_base FROM marcaciones_resumen WHERE fecha IN ($ph)");
$stmtM->execute($diasRevisar);

$marcas = [];
foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $marcas[$r['rut_base']][$r['fecha']] = true;
}

// 3. Preparar Excel (CSV)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$tituloArchivo.'"');
$output = fopen('php://output', 'w');
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF))); // Soporte de tildes

// Generar cabeceras dinámicas
$cabeceras = ['Nombre', 'RUT', 'Dpto.', 'Número'];
foreach ($diasRevisar as $dia) {
    $cabeceras[] = date('d/m/Y', strtotime($dia)); // Ej: 05/04/2026
}
fputcsv($output, $cabeceras, ';');

// 4. Llenar la matriz
$hoy = date('Y-m-d'); // Para no evaluar días en el futuro

foreach ($empleados as $emp) {
    $rut = $emp['rut_base'];
    $fila = [
        $emp['nombre'], 
        $rut, 
        $emp['dpto'], 
        $emp['numero']
    ];
    
    $tuvoFalta = false;
    
    // Rellenamos las celdas de los días
    foreach ($diasRevisar as $dia) {
        if ($dia > $hoy) {
            // El día no ha ocurrido aún, no es falta
            $fila[] = '-'; 
        } elseif (!isset($marcas[$rut][$dia])) {
            $fila[] = 'FALTÓ';
            $tuvoFalta = true;
        } else {
            $fila[] = 'OK';
        }
    }

    // Solo exportar a la persona si faltó al menos 1 día hábil (en el pasado/hoy)
    if ($tuvoFalta) {
        fputcsv($output, $fila, ';');
    }
}

fclose($output);
exit;
?>