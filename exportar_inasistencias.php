<?php
/**
 * exportar_inasistencias.php
 * Genera un archivo .xlsx coloreado con las inasistencias del período seleccionado.
 *
 * Colores:
 *  ✅ OK      → Verde claro  (#D1FAE5) con texto verde oscuro
 *  ❌ FALTÓ   → Rojo claro   (#FEE2E2) con texto rojo oscuro en negrita
 *  ⬜ Futuro  → Gris neutro  (#F9FAFB) con texto gris
 *  📋 Header  → Azul oscuro  (#1E3A8A) con texto blanco
 *  📅 Fechas  → Azul claro   (#DBEAFE) con texto azul oscuro
 *  📊 Totales → Ámbar claro  (#FEF3C7) con texto ámbar oscuro
 */

ob_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inc/xlsx_generator.php'; // ← Exportador Excel inteligente (XLSX / XLS / CSV)
date_default_timezone_set('America/Santiago');

try {
    $pdo = db();

    // ── Parámetros de entrada ──────────────────────────────────────────────────
    $rango    = isset($_GET['rango']) ? $_GET['rango'] : 'semana';
    $mes      = isset($_GET['mes'])   ? trim($_GET['mes'])   : date('Y-m');
    $fechaSel = isset($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-d');

    // ── Crear exportador (elige XLSX/XLS/CSV según el servidor) ─────────────
    // Debe instanciarse antes de construir los nombres de archivo para obtener
    // la extensión correcta (.xlsx si ZipArchive está disponible, .xls si no).
    $xlsx = ExcelExporter::create();

    // ── Calcular rango de días a revisar ──────────────────────────────────────
    $diasRevisar    = array();
    $tituloArchivo  = '';
    $tituloPeriodo  = '';

    if ($rango === 'semana') {
        $selDate = new DateTime($fechaSel);
        $selDOW  = (int)$selDate->format('N');

        $lunes   = clone $selDate;
        $lunes->modify('-' . ($selDOW - 1) . ' days');
        $viernes = clone $lunes;
        $viernes->modify('+4 days');

        $numSemana = (int)$lunes->format('W');
        for ($i = 0; $i < 5; $i++) {
            $d = clone $lunes;
            $d->modify("+$i days");
            $diasRevisar[] = $d->format('Y-m-d');
        }

        $tituloArchivo = 'Inasistencias_Sem' . $numSemana
                       . '_' . $lunes->format('d-m-Y')
                       . '_al_' . $viernes->format('d-m-Y') . $xlsx->getExtension();

        $tituloPeriodo = 'Semana ' . $numSemana
                       . ' — del ' . $lunes->format('d/m/Y')
                       . ' al ' . $viernes->format('d/m/Y');
    } else {
        $inicioMes = new DateTime($mes . '-01');
        $finMes    = new DateTime($mes . '-01');
        $finMes->modify('last day of this month');
        $finMes->modify('+1 day');

        $periodo = new DatePeriod($inicioMes, new DateInterval('P1D'), $finMes);
        foreach ($periodo as $dt) {
            if ((int)$dt->format('N') <= 5) {  // Solo lunes a viernes
                $diasRevisar[] = $dt->format('Y-m-d');
            }
        }

        $mesesNombres = array(
            1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        );
        list($anio, $indiceMes) = explode('-', $mes);
        $nombreMes = $mesesNombres[(int)$indiceMes];

        $tituloArchivo = 'Inasistencias_' . $nombreMes . '_' . $anio . $xlsx->getExtension();
        $tituloPeriodo = $nombreMes . ' ' . $anio;
    }

    // ── Consultar empleados (mes actual + mes anterior para capturar todos) ───
    $mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));

    $stmtEmp = $pdo->prepare(
        "SELECT DISTINCT rut_base, nombre, dpto, numero
         FROM marcaciones_resumen
         WHERE DATE_FORMAT(fecha, '%Y-%m') IN (?, ?)
         ORDER BY dpto ASC, nombre ASC"
    );
    $stmtEmp->execute(array($mes, $mesAnterior));
    $empleados = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

    // ── Consultar marcaciones del período ─────────────────────────────────────
    $marcas = array();
    if (!empty($diasRevisar)) {
        $ph      = implode(',', array_fill(0, count($diasRevisar), '?'));
        $stmtMar = $pdo->prepare(
            "SELECT fecha, rut_base
             FROM marcaciones_resumen
             WHERE fecha IN ($ph)"
        );
        $stmtMar->execute($diasRevisar);
        foreach ($stmtMar->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $marcas[$r['rut_base']][$r['fecha']] = true;
        }
    }

    // ── Calcular contadores de faltas por día (para fila de totales) ──────────
    $hoy         = date('Y-m-d');
    $faltasPorDia = array_fill(0, count($diasRevisar), 0);

    // Pre-calcular filas de datos para saber qué empleados tuvieron faltas
    $filasDatos = array();
    foreach ($empleados as $emp) {
        $rut      = $emp['rut_base'];
        $tuvoFalta = false;
        $celdas   = array();

        foreach ($diasRevisar as $idxDia => $dia) {
            if ($dia > $hoy) {
                $celdas[] = array('value' => '—', 'style' => ExcelExporter::STYLE_FUTURO);
            } elseif (!isset($marcas[$rut][$dia])) {
                $celdas[]             = array('value' => 'FALTÓ', 'style' => ExcelExporter::STYLE_FALTA);
                $faltasPorDia[$idxDia]++;
                $tuvoFalta = true;
            } else {
                $celdas[] = array('value' => '✓ OK', 'style' => ExcelExporter::STYLE_OK);
            }
        }

        if ($tuvoFalta) {
            $filasDatos[] = array(
                'emp'    => $emp,
                'celdas' => $celdas,
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Construir el XLSX
    // ════════════════════════════════════════════════════════════════════════════
    // Los anchos de columna se calcularán automáticamente en autoFitColumns() más abajo.

    // ── FILA 1: Título del reporte ────────────────────────────────────────────
    $totalCols  = 4 + count($diasRevisar);
    $filaTitle  = array();
    $filaTitle[] = array(
        'value' => 'REPORTE DE INASISTENCIAS — ' . strtoupper($tituloPeriodo),
        'style' => ExcelExporter::STYLE_HEADER,
    );
    for ($i = 1; $i < $totalCols; $i++) {
        $filaTitle[] = array('value' => '', 'style' => ExcelExporter::STYLE_HEADER);
    }
    $xlsx->addRow($filaTitle);

    // ── FILA 2: Subtítulo / metadata ──────────────────────────────────────────
    $fechaGen   = date('d/m/Y H:i');
    $filaSub    = array();
    $filaSub[]  = array(
        'value' => 'Generado el ' . $fechaGen . '   |   Total empleados con falta: ' . count($filasDatos),
        'style' => ExcelExporter::STYLE_TOTALES,
    );
    for ($i = 1; $i < $totalCols; $i++) {
        $filaSub[] = array('value' => '', 'style' => ExcelExporter::STYLE_TOTALES);
    }
    $xlsx->addRow($filaSub);

    // ── FILA 3: Cabecera de columnas ──────────────────────────────────────────
    $filaCab    = array();
    $filaCab[]  = array('value' => 'Nombre Funcionario',  'style' => ExcelExporter::STYLE_HEADER);
    $filaCab[]  = array('value' => 'RUT',                 'style' => ExcelExporter::STYLE_HEADER);
    $filaCab[]  = array('value' => 'Departamento',        'style' => ExcelExporter::STYLE_HEADER);
    $filaCab[]  = array('value' => 'N° Empleado',         'style' => ExcelExporter::STYLE_HEADER);

    $diasSemana = array('', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM');
    foreach ($diasRevisar as $dia) {
        $dt      = new DateTime($dia);
        $label   = $diasSemana[(int)$dt->format('N')] . "\n" . $dt->format('d/m');
        $filaCab[] = array('value' => $label, 'style' => ExcelExporter::STYLE_DATE_HEADER);
    }
    $xlsx->addRow($filaCab);

    // ── FILAS DE DATOS (solo empleados con al menos 1 falta) ─────────────────
    if (empty($filasDatos)) {
        // Sin inasistencias — fila informativa
        $filaVacia   = array();
        $filaVacia[] = array('value' => '✓  No se registraron inasistencias en este período.', 'style' => ExcelExporter::STYLE_OK);
        for ($i = 1; $i < $totalCols; $i++) {
            $filaVacia[] = array('value' => '', 'style' => ExcelExporter::STYLE_OK);
        }
        $xlsx->addRow($filaVacia);
    } else {
        foreach ($filasDatos as $fd) {
            $emp  = $fd['emp'];
            $fila = array();
            $fila[] = array('value' => $emp['nombre'],  'style' => ExcelExporter::STYLE_INFO);
            $fila[] = array('value' => $emp['rut_base'], 'style' => ExcelExporter::STYLE_INFO, 'force_string' => true);
            $fila[] = array('value' => $emp['dpto'],    'style' => ExcelExporter::STYLE_INFO);
            $fila[] = array('value' => $emp['numero'],  'style' => ExcelExporter::STYLE_INFO, 'force_string' => true);

            foreach ($fd['celdas'] as $celda) {
                $fila[] = $celda;
            }
            $xlsx->addRow($fila);
        }
    }

    // ── FILA DE TOTALES (faltas por día) ──────────────────────────────────────
    $filaTot   = array();
    $filaTot[] = array('value' => 'TOTAL INASISTENCIAS POR DÍA', 'style' => ExcelExporter::STYLE_TOTALES);
    $filaTot[] = array('value' => '',                             'style' => ExcelExporter::STYLE_TOTALES);
    $filaTot[] = array('value' => '',                             'style' => ExcelExporter::STYLE_TOTALES);
    $filaTot[] = array('value' => '',                             'style' => ExcelExporter::STYLE_TOTALES);

    foreach ($diasRevisar as $idxDia => $dia) {
        $cnt       = $faltasPorDia[$idxDia];
        $estilo    = ($dia > $hoy) ? ExcelExporter::STYLE_FUTURO : ExcelExporter::STYLE_TOTALES;
        $filaTot[] = array('value' => ($dia > $hoy ? '—' : (string)$cnt), 'style' => $estilo);
    }
    $xlsx->addRow($filaTot);

    // ── Ajuste automático de anchos y altos por contenido ─────────────────────
    // autoFitColumns: analiza el texto más largo de cada columna,
    //                 compensa el 10% extra de las fuentes en negrita.
    // autoFitRows:    debe llamarse DESPUÉS de autoFitColumns porque usa
    //                 los anchos ya calculados para estimar el wrap de texto.
    $xlsx->autoFitColumns(10.0, 55.0);
    $xlsx->autoFitRows();

    // ── Generar binario y enviar al navegador ─────────────────────────────────
    if (ob_get_length()) {
        ob_end_clean();
    }

    $xlsxData = $xlsx->generate();

    header('Content-Type: ' . $xlsx->getContentType());
    header('Content-Disposition: attachment; filename="' . $tituloArchivo . '"');
    header('Content-Length: ' . strlen($xlsxData));
    header('Cache-Control: max-age=0');

    echo $xlsxData;
    exit;

} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    die('Error al generar el archivo: ' . htmlspecialchars($e->getMessage()));
}