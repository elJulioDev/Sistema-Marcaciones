<?php
ob_start(); // Inicia el búfer para atrapar cualquier espacio en blanco o BOM oculto
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php'; 
date_default_timezone_set('America/Santiago');

try {
    $pdo = db();

    $rango    = isset($_GET['rango']) ? $_GET['rango'] : 'semana';
    $mes      = isset($_GET['mes']) ? trim($_GET['mes']) : date('Y-m');
    $fechaSel = isset($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-d');

    $diasRevisar = [];
    $tituloArchivo = '';

    if ($rango === 'semana') {
        $selDate = new DateTime($fechaSel);
        $selDOW  = (int)$selDate->format('N');
        $lunes = clone $selDate;
        $lunes->modify('-'.($selDOW-1).' days');
        $viernes = clone $lunes;
        $viernes->modify('+4 days');
        
        $numSemana = $lunes->format('W'); 
        for ($i = 0; $i < 5; $i++) {
            $d = clone $lunes;
            $d->modify("+$i days");
            $diasRevisar[] = $d->format('Y-m-d');
        }
        $tituloArchivo = 'Inasistencias_Sem_'.$numSemana.'_del_'.$lunes->format('d-m-Y').'_al_'.$viernes->format('d-m-Y').'.csv';
    } else {
        $inicioMes = new DateTime($mes . '-01');
        $finMes = new DateTime($mes . '-01');
        $finMes->modify('last day of this month');
        $finMes->modify('+1 day'); 
        $periodo = new DatePeriod($inicioMes, new DateInterval('P1D'), $finMes);
        foreach ($periodo as $dt) {
            if ($dt->format('N') <= 5) {
                $diasRevisar[] = $dt->format('Y-m-d');
            }
        }
        $mesesNombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $mesParts = explode('-', $mes);
        $indiceMes = (int)$mesParts[1];
        $nombreMes = $mesesNombres[$indiceMes];
        $anio = $mesParts[0];
        $tituloArchivo = 'Inasistencias_Mes_' . $nombreMes . '_' . $anio . '.csv';
    }

    $mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));
    $stmtBase = $pdo->prepare("SELECT DISTINCT rut_base, nombre, dpto, numero FROM marcaciones_resumen WHERE DATE_FORMAT(fecha,'%Y-%m') IN (?, ?) ORDER BY nombre ASC");
    $stmtBase->execute([$mes, $mesAnterior]);
    $empleados = $stmtBase->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($diasRevisar)) {
        $ph = implode(',', array_fill(0, count($diasRevisar), '?'));
        $stmtM = $pdo->prepare("SELECT fecha, rut_base FROM marcaciones_resumen WHERE fecha IN ($ph)");
        $stmtM->execute($diasRevisar);

        $marcas = [];
        foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $marcas[$r['rut_base']][$r['fecha']] = true;
        }
    } else {
        $marcas = [];
    }

    // LIMPIEZA TOTAL: Destruimos cualquier texto basura que se haya impreso sin querer
    if (ob_get_length()) ob_end_clean();

    // Forzamos la descarga del archivo CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$tituloArchivo.'"');
    $output = fopen('php://output', 'w');

    $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
    fputs($output, $bom); 

    $cabeceras = ['Nombre', 'RUT', 'Dpto.', 'Número'];
    foreach ($diasRevisar as $dia) {
        $cabeceras[] = date('d/m/Y', strtotime($dia));
    }
    fputcsv($output, $cabeceras, ';');

    $hoy = date('Y-m-d');
    $algunaFalta = false;

    foreach ($empleados as $emp) {
        $rut = $emp['rut_base'];
        $fila = [$emp['nombre'], $rut, $emp['dpto'], $emp['numero']];
        $tuvoFalta = false;
        
        foreach ($diasRevisar as $dia) {
            if ($dia > $hoy) {
                $fila[] = '-'; 
            } elseif (!isset($marcas[$rut][$dia])) {
                $fila[] = 'FALTÓ';
                $tuvoFalta = true;
            } else {
                $fila[] = 'OK';
            }
        }

        if ($tuvoFalta) {
            fputcsv($output, $fila, ';');
            $algunaFalta = true;
        }
    }

    if (!$algunaFalta) {
        fputcsv($output, ['No se registraron inasistencias en este periodo.'], ';');
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    // Si hay un error real de base de datos, lo mostramos en pantalla
    if (ob_get_length()) ob_end_clean();
    die("Error al generar el archivo: " . $e->getMessage());
}
?>