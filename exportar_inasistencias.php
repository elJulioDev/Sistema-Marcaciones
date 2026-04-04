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
 *
 * LÓGICA DE COLUMNAS DE FIN DE SEMANA:
 *  - Lunes a Viernes: siempre se incluyen como columnas.
 *  - Sábado y Domingo: solo se incluyen si existe AL MENOS UN registro
 *    de marcación en esa fecha dentro del período seleccionado.
 *
 * LÓGICA DE EMPLEADOS:
 *  - Solo se listan empleados que tienen al menos un registro dentro de
 *    los días efectivamente incluidos ($diasRevisar). Nunca se consulta
 *    el mes anterior ni períodos ajenos a la selección actual.
 */

ob_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inc/xlsx_generator.php';
date_default_timezone_set('America/Santiago');

try {
    $pdo = db();

    // ── Parámetros de entrada ──────────────────────────────────────────────────
    $rango    = isset($_GET['rango']) ? $_GET['rango'] : 'semana';
    $mes      = isset($_GET['mes'])   ? trim($_GET['mes'])   : date('Y-m');
    $fechaSel = isset($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-d');

    // ── Crear exportador ─────────────────────────────────────────────────────
    $xlsx = ExcelExporter::create();

    // ── 1. Determinar fechas candidatas del período ───────────────────────────
    $fechasCandidatas = array();

    if ($rango === 'semana') {
        $selDate = new DateTime($fechaSel);
        $selDOW  = (int)$selDate->format('N');

        $lunes = clone $selDate;
        $lunes->modify('-' . ($selDOW - 1) . ' days');
        $numSemana = (int)$lunes->format('W');

        // Los 7 días de la semana (Lun → Dom)
        for ($i = 0; $i < 7; $i++) {
            $d = clone $lunes;
            $d->modify("+$i days");
            $fechasCandidatas[] = $d->format('Y-m-d');
        }
    } else {
        $inicioMes = new DateTime($mes . '-01');
        $finMes    = new DateTime($mes . '-01');
        $finMes->modify('last day of this month');
        $finMes->modify('+1 day');

        $periodo = new DatePeriod($inicioMes, new DateInterval('P1D'), $finMes);
        foreach ($periodo as $dt) {
            $fechasCandidatas[] = $dt->format('Y-m-d');
        }
    }

    // ── 2. Consultar qué días candidatos tienen al menos 1 marcación ──────────
    $diasConMarcas = array();
    if (!empty($fechasCandidatas)) {
        $ph     = implode(',', array_fill(0, count($fechasCandidatas), '?'));
        $stmtF  = $pdo->prepare(
            "SELECT DISTINCT fecha FROM marcaciones_resumen WHERE fecha IN ($ph)"
        );
        $stmtF->execute($fechasCandidatas);
        foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $diasConMarcas[$r['fecha']] = true;
        }
    }

    // ── 3. Filtrar candidatas ─────────────────────────────────────────────────
    //   · Lunes–Viernes → siempre incluir.
    //   · Sábado (N=6) / Domingo (N=7) → solo si hay al menos 1 marcación ese día.
    $diasRevisar = array();
    foreach ($fechasCandidatas as $f) {
        $esFinde = (date('N', strtotime($f)) >= 6);
        if (!$esFinde || isset($diasConMarcas[$f])) {
            $diasRevisar[] = $f;
        }
    }

    // ── 4. Títulos dinámicos ──────────────────────────────────────────────────
    if ($rango === 'semana') {
        $primerDia = new DateTime($diasRevisar[0]);
        $ultimoDia = new DateTime(end($diasRevisar));

        $tituloArchivo = 'Reporte_Sem' . $numSemana
                       . '_' . $primerDia->format('d-m-Y')
                       . '_al_' . $ultimoDia->format('d-m-Y') . $xlsx->getExtension();

        $tituloPeriodo = 'Semana ' . $numSemana
                       . ' — del ' . $primerDia->format('d/m/Y')
                       . ' al '    . $ultimoDia->format('d/m/Y');
    } else {
        $mesesNombres = array(
            1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        );
        list($anio, $indiceMes) = explode('-', $mes);
        $nombreMes = $mesesNombres[(int)$indiceMes];

        $tituloArchivo = 'Inasistencias_' . $nombreMes . '_' . $anio . $xlsx->getExtension();
        $tituloPeriodo = $nombreMes . ' ' . $anio;
    }

    // ── 5. Consultar empleados ────────────────────────────────────────────────
    //   CORRECCIÓN PRINCIPAL: solo se consultan empleados que tienen registros
    //   exactamente en los días de $diasRevisar. Nunca se mezcla con meses
    //   anteriores ni con fechas fuera del período seleccionado.
    $empleados = array();
    if (!empty($diasRevisar)) {
        $ph       = implode(',', array_fill(0, count($diasRevisar), '?'));
        $stmtEmp  = $pdo->prepare(
            "SELECT DISTINCT rut_base, nombre, dpto, numero
             FROM marcaciones_resumen
             WHERE fecha IN ($ph)
             ORDER BY dpto ASC, nombre ASC"
        );
        $stmtEmp->execute($diasRevisar);
        $empleados = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── 6. Consultar marcaciones del período ──────────────────────────────────
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

    // ── 7. Construir filas de datos ───────────────────────────────────────────
    //   Un empleado se incluye en el reporte si:
    //     a) Faltó al menos un día hábil (Lun–Vie), O
    //     b) Trabajó al menos un fin de semana incluido en $diasRevisar.
    $hoy          = date('Y-m-d');
    $faltasPorDia = array_fill(0, count($diasRevisar), 0);
    $filasDatos   = array();

    foreach ($empleados as $emp) {
        $rut           = $emp['rut_base'];
        $tuvoFalta     = false;
        $tuvoMarcaFinde = false;
        $celdas        = array();

        foreach ($diasRevisar as $idxDia => $dia) {
            $esFinde = (date('N', strtotime($dia)) >= 6);

            if ($dia > $hoy) {
                // Día futuro → celda neutra
                $celdas[] = array('value' => '—', 'style' => ExcelExporter::STYLE_FUTURO);
            } elseif (isset($marcas[$rut][$dia])) {
                // Tiene marcación → OK
                $celdas[] = array('value' => '✓ OK', 'style' => ExcelExporter::STYLE_OK);
                if ($esFinde) {
                    $tuvoMarcaFinde = true;
                }
            } else {
                if ($esFinde) {
                    // Fin de semana sin marcación → descanso (no es falta)
                    $celdas[] = array('value' => '—', 'style' => ExcelExporter::STYLE_FUTURO);
                } else {
                    // Día hábil sin marcación → FALTÓ
                    $celdas[] = array('value' => 'FALTÓ', 'style' => ExcelExporter::STYLE_FALTA);
                    $faltasPorDia[$idxDia]++;
                    $tuvoFalta = true;
                }
            }
        }

        if ($tuvoFalta || $tuvoMarcaFinde) {
            $filasDatos[] = array(
                'emp'    => $emp,
                'celdas' => $celdas,
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Construir el XLSX
    // ════════════════════════════════════════════════════════════════════════════

    $totalCols = 4 + count($diasRevisar);

    // ── FILA 1: Título ────────────────────────────────────────────────────────
    $filaTitle   = array();
    $filaTitle[] = array(
        'value' => 'REPORTE DE INASISTENCIAS — ' . strtoupper($tituloPeriodo),
        'style' => ExcelExporter::STYLE_HEADER,
    );
    for ($i = 1; $i < $totalCols; $i++) {
        $filaTitle[] = array('value' => '', 'style' => ExcelExporter::STYLE_HEADER);
    }
    $xlsx->addRow($filaTitle);

    // ── FILA 2: Subtítulo / metadata ──────────────────────────────────────────
    $fechaGen  = date('d/m/Y H:i');
    $filaSub   = array();
    $filaSub[] = array(
        'value' => 'Generado el ' . $fechaGen . '   |   Total empleados listados: ' . count($filasDatos),
        'style' => ExcelExporter::STYLE_TOTALES,
    );
    for ($i = 1; $i < $totalCols; $i++) {
        $filaSub[] = array('value' => '', 'style' => ExcelExporter::STYLE_TOTALES);
    }
    $xlsx->addRow($filaSub);

    // ── FILA 3: Cabecera de columnas ──────────────────────────────────────────
    $filaCab   = array();
    $filaCab[] = array('value' => 'Nombre Funcionario', 'style' => ExcelExporter::STYLE_HEADER);
    $filaCab[] = array('value' => 'RUT',                'style' => ExcelExporter::STYLE_HEADER);
    $filaCab[] = array('value' => 'Departamento',       'style' => ExcelExporter::STYLE_HEADER);
    $filaCab[] = array('value' => 'N° Empleado',        'style' => ExcelExporter::STYLE_HEADER);

    $diasSemana = array('', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM');
    foreach ($diasRevisar as $dia) {
        $dt      = new DateTime($dia);
        $label   = $diasSemana[(int)$dt->format('N')] . "\n" . $dt->format('d/m');
        $filaCab[] = array('value' => $label, 'style' => ExcelExporter::STYLE_DATE_HEADER);
    }
    $xlsx->addRow($filaCab);

    // ── FILAS DE DATOS ────────────────────────────────────────────────────────
    if (empty($filasDatos)) {
        $filaVacia   = array();
        $filaVacia[] = array(
            'value' => '✓  No se registraron inasistencias ni marcas de fin de semana en este período.',
            'style' => ExcelExporter::STYLE_OK,
        );
        for ($i = 1; $i < $totalCols; $i++) {
            $filaVacia[] = array('value' => '', 'style' => ExcelExporter::STYLE_OK);
        }
        $xlsx->addRow($filaVacia);
    } else {
        foreach ($filasDatos as $fd) {
            $emp  = $fd['emp'];
            $fila = array();
            $fila[] = array('value' => $emp['nombre'],   'style' => ExcelExporter::STYLE_INFO);
            $fila[] = array('value' => $emp['rut_base'], 'style' => ExcelExporter::STYLE_INFO, 'force_string' => true);
            $fila[] = array('value' => $emp['dpto'],     'style' => ExcelExporter::STYLE_INFO);
            $fila[] = array('value' => $emp['numero'],   'style' => ExcelExporter::STYLE_INFO, 'force_string' => true);

            foreach ($fd['celdas'] as $celda) {
                $fila[] = $celda;
            }
            $xlsx->addRow($fila);
        }
    }

    // ── FILA DE TOTALES ───────────────────────────────────────────────────────
    $filaTot   = array();
    $filaTot[] = array('value' => 'TOTAL INASISTENCIAS POR DÍA', 'style' => ExcelExporter::STYLE_TOTALES);
    $filaTot[] = array('value' => '',                             'style' => ExcelExporter::STYLE_TOTALES);
    $filaTot[] = array('value' => '',                             'style' => ExcelExporter::STYLE_TOTALES);
    $filaTot[] = array('value' => '',                             'style' => ExcelExporter::STYLE_TOTALES);

    foreach ($diasRevisar as $idxDia => $dia) {
        $cnt     = $faltasPorDia[$idxDia];
        $esFinde = (date('N', strtotime($dia)) >= 6);
        // En fines de semana no hay "faltas" contadas → mostrar guión
        if ($dia > $hoy || $esFinde) {
            $filaTot[] = array('value' => '—', 'style' => ExcelExporter::STYLE_FUTURO);
        } else {
            $filaTot[] = array('value' => (string)$cnt, 'style' => ExcelExporter::STYLE_TOTALES);
        }
    }
    $xlsx->addRow($filaTot);

    // ── Ajuste de anchos / altos ───────────────────────────────────────────────
    $xlsx->autoFitColumns(10.0, 55.0);
    $xlsx->autoFitRows();

    // ── Enviar al navegador ────────────────────────────────────────────────────
    if (ob_get_length()) {
        ob_end_clean();
    }

    $xlsxData = $xlsx->generate();

    header('Content-Type: '        . $xlsx->getContentType());
    header('Content-Disposition: attachment; filename="' . $tituloArchivo . '"');
    header('Content-Length: '      . strlen($xlsxData));
    header('Cache-Control: max-age=0');

    echo $xlsxData;
    exit;

} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    die('Error al generar el archivo: ' . htmlspecialchars($e->getMessage()));
}