<?php
/**
 * exportar_horas_mes.php
 * Genera un archivo .xlsx con el detalle de horas trabajadas por empleado en el mes.
 *
 * Estructura del reporte:
 *   Columnas fijas : N°  |  Nombre  |  RUT  |  Departamento
 *   Columnas días  : Una por cada día del mes (1 → último día)
 *                    Solo se incluyen sábados/domingos si hubo alguna marcación ese día.
 *   Columnas resumen:
 *     - Días Trabajados   : días con algún registro (OK, OBSERVADO, INCOMPLETO)
 *     - Total Horas       : suma de total_horas válidas
 *     - Horas Esperadas   : días hábiles pasados × 8 h
 *     - Diferencia        : Total Horas − Horas Esperadas
 *
 * Colores por estado de celda diaria:
 *   ✅ OK / OBSERVADO → verde   (STYLE_OK)
 *   ⚠️  INCOMPLETO    → ámbar   (STYLE_TOTALES)
 *   ❌ ERROR / FALTÓ  → rojo    (STYLE_FALTA)
 *   ⬜ Fin de semana / Futuro → gris (STYLE_FUTURO)
 *   📊 Columnas resumen       → ámbar (STYLE_TOTALES)
 *   ➕ Diferencia positiva    → verde (STYLE_OK)
 *   ➖ Diferencia negativa    → rojo  (STYLE_FALTA)
 */

ob_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inc/xlsx_generator.php';
date_default_timezone_set('America/Santiago');

/* ═══════════════════════════════════════════════════════════
 * Función auxiliar: convierte "HH:MM:SS" o "HH:MM" a minutos
 * ═══════════════════════════════════════════════════════════ */
function hms_a_minutos($hms) {
    if (empty($hms)) return 0;
    $parts = explode(':', trim($hms));
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    $m = isset($parts[1]) ? (int)$parts[1] : 0;
    return ($h * 60) + $m;
}

/* ═══════════════════════════════════════════════════════════
 * Función auxiliar: convierte minutos a "Xh YYm"
 * ═══════════════════════════════════════════════════════════ */
function minutos_a_hhmm_display($minutos) {
    if ($minutos <= 0) return '0h 00m';
    $h = floor($minutos / 60);
    $m = $minutos % 60;
    return $h . 'h ' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
}

try {
    $pdo = db();

    /* ── Parámetros de entrada ──────────────────────────────── */
    $mes   = isset($_GET['mes'])   ? trim($_GET['mes'])   : date('Y-m');
    $dpto  = isset($_GET['dpto'])  ? trim($_GET['dpto'])  : '';
    $q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';

    // Validación básica del formato YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $mes = date('Y-m');
    }

    /* ── Rango del mes ──────────────────────────────────────── */
    $inicioMes = new DateTime($mes . '-01');
    $finMes    = clone $inicioMes;
    $finMes->modify('last day of this month');

    $inicioSQL = $inicioMes->format('Y-m-d');
    $finSQL    = $finMes->format('Y-m-d');
    $hoy       = date('Y-m-d');

    /* ── Título y nombre de archivo ─────────────────────────── */
    $MESES = array(
        '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
        '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
        '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre',
    );
    $partesMes  = explode('-', $mes);
    $nombreMes  = $MESES[$partesMes[1]] . ' ' . $partesMes[0];
    $tituloPeriodo = 'REPORTE DE HORAS TRABAJADAS — ' . strtoupper($nombreMes);
    $sufijoDpto    = $dpto ? '_' . preg_replace('/[^a-zA-Z0-9]/', '', $dpto) : '';
    $tituloArchivo = 'horas_' . str_replace('-', '_', $mes) . $sufijoDpto . '.xlsx';

    /* ── 1. Todos los días del mes ──────────────────────────── */
    $todosDias = array();
    $finMesClon = clone $finMes;
    $finMesClon->modify('+1 day');
    $periodo   = new DatePeriod($inicioMes, new DateInterval('P1D'), $finMesClon);
    foreach ($periodo as $dt) {
        $todosDias[] = $dt->format('Y-m-d');
    }

    /* ── 2. ¿Qué fines de semana tienen marcaciones? ─────────── */
    $ph      = implode(',', array_fill(0, count($todosDias), '?'));
    $params  = $todosDias;
    $stmt    = $pdo->prepare(
        "SELECT DISTINCT fecha FROM marcaciones_resumen WHERE fecha IN ($ph)"
    );
    $stmt->execute($params);
    $diasConMarcas = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    /* ── 3. Filtrar días a incluir como columnas ─────────────── */
    // Lun–Vie: siempre. Sáb–Dom: solo si hubo marcación ese día.
    $diasColumna = array();
    foreach ($todosDias as $dia) {
        $dtAux = new DateTime($dia);
        $dow = (int)$dtAux->format('N'); // 1=Lun, 7=Dom
        $esFinde = ($dow >= 6);
        if (!$esFinde || isset($diasConMarcas[$dia])) {
            $diasColumna[] = $dia;
        }
    }

    /* ── 4. Consultar marcaciones_resumen del mes ────────────── */
    $phCols   = implode(',', array_fill(0, count($diasColumna), '?'));
    $paramsQ  = $diasColumna;
    $whereSQL = "fecha IN ($phCols)";
    if ($dpto !== '') {
        $whereSQL .= ' AND dpto = ?';
        $paramsQ[] = $dpto;
    }
    if ($q !== '') {
        $whereSQL .= ' AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)';
        $like = '%' . $q . '%';
        $paramsQ[] = $like;
        $paramsQ[] = $like;
        $paramsQ[] = $like;
    }

    $stmtM = $pdo->prepare(
        "SELECT rut_base, numero, nombre, dpto, fecha,
                entrada, salida, total_horas, cantidad_marcaciones, estado
         FROM marcaciones_resumen
         WHERE $whereSQL
         ORDER BY nombre ASC, fecha ASC"
    );
    $stmtM->execute($paramsQ);

    /* ── 5. Construir estructura indexada: [rut][fecha] ─────── */
    $marcasPorEmp = array();  // [rut_base][fecha] = registro
    $infoEmp      = array();  // [rut_base] = {nombre, dpto, numero}

    foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rut = $r['rut_base'];
        $marcasPorEmp[$rut][$r['fecha']] = $r;
        if (!isset($infoEmp[$rut])) {
            $infoEmp[$rut] = array(
                'nombre' => $r['nombre'],
                'dpto'   => $r['dpto'],
                'numero' => $r['numero'],
                'rut'    => $rut,
            );
        }
    }

    // Ordenar empleados por nombre
    uasort($infoEmp, function($a, $b) { return strcmp($a['nombre'], $b['nombre']); });

    /* ── 6. Calcular días hábiles pasados (para horas esperadas) */
    $diasHabilesPasados = 0;
    foreach ($diasColumna as $dia) {
        $dtAux = new DateTime($dia);
        $dow = (int)$dtAux->format('N');
        $esFinde = ($dow >= 6);
        if (!$esFinde && $dia <= $hoy) {
            $diasHabilesPasados++;
        }
    }
    $minutosEsperadosPorEmp = $diasHabilesPasados * 8 * 60; // 8h por día hábil

    /* ── 7. Crear exportador ────────────────────────────────── */
    $xlsx = ExcelExporter::create();

    $totalCols = 4 + count($diasColumna) + 4; // 4 fijas + días + 4 resumen

    /* ── FILA 1: Título (combinada sobre todas las columnas) ── */
    $fila = array();
    $fila[] = array(
        'value'   => $tituloPeriodo,
        'style'   => ExcelExporter::STYLE_HEADER,
        'colspan' => $totalCols,
    );
    for ($i = 1; $i < $totalCols; $i++) {
        $fila[] = array('value' => '', 'style' => ExcelExporter::STYLE_HEADER);
    }
    $xlsx->addRow($fila);

    /* ── FILA 2: Metadatos (combinada sobre todas las columnas) ── */
    $filtroInfo = 'Todos los departamentos';
    if ($dpto) $filtroInfo = 'Depto: ' . $dpto;
    if ($q)    $filtroInfo .= ($dpto ? ' | ' : '') . 'Búsqueda: "' . $q . '"';

    $meta  = 'Generado: ' . date('d/m/Y H:i');
    $meta .= '   |   Período: ' . $inicioMes->format('d/m/Y') . ' al ' . $finMes->format('d/m/Y');
    $meta .= '   |   Días hábiles pasados: ' . $diasHabilesPasados;
    $meta .= '   |   ' . $filtroInfo;

    $fila = array();
    $fila[] = array(
        'value'   => $meta,
        'style'   => ExcelExporter::STYLE_INFO,
        'colspan' => $totalCols,
    );
    for ($i = 1; $i < $totalCols; $i++) {
        $fila[] = array('value' => '', 'style' => ExcelExporter::STYLE_INFO);
    }
    $xlsx->addRow($fila);

    /* ── FILA 3: Cabeceras de columnas ──────────────────────── */
    $DIAS_ABREV = array('', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do');
    $MESES_ABREV = array('', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                              'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic');

    $fila = array();
    $fila[] = array('value' => 'N°',           'style' => ExcelExporter::STYLE_HEADER);
    $fila[] = array('value' => 'Nombre',        'style' => ExcelExporter::STYLE_HEADER);
    $fila[] = array('value' => 'RUT',           'style' => ExcelExporter::STYLE_HEADER);
    $fila[] = array('value' => 'Departamento',  'style' => ExcelExporter::STYLE_HEADER);

    foreach ($diasColumna as $dia) {
        $dt  = new DateTime($dia);
        $dow = (int)$dt->format('N');
        $label = $DIAS_ABREV[$dow] . "\n" . (int)$dt->format('j') . '/' . $MESES_ABREV[(int)$dt->format('n')];
        $fila[] = array('value' => $label, 'style' => ExcelExporter::STYLE_DATE_HEADER);
    }

    // Cabeceras de resumen
    $fila[] = array('value' => "Días\nTrabajados",   'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => "Total\nHoras",        'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => "Horas\nEsperadas",    'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => "Diferencia\n(+/−)",   'style' => ExcelExporter::STYLE_TOTALES);
    $xlsx->addRow($fila);

    /* ── FILAS DE DATOS ─────────────────────────────────────── */
    // Acumuladores para fila de totales globales
    $totalGlobalDiasTrab  = 0;
    $totalGlobalMinutos   = 0;
    $totalGlobalEsperados = 0;
    $faltasPorDia = array_fill(0, count($diasColumna), 0); // para la fila de totales
    $numFila      = 1;

    if (empty($infoEmp)) {
        // Sin datos
        $fila = array();
        $fila[] = array(
            'value' => 'Sin registros para el período y filtros seleccionados.',
            'style' => ExcelExporter::STYLE_FUTURO,
        );
        for ($i = 1; $i < $totalCols; $i++) {
            $fila[] = array('value' => '', 'style' => ExcelExporter::STYLE_FUTURO);
        }
        $xlsx->addRow($fila);
    } else {
        foreach ($infoEmp as $rut => $emp) {
            $fila = array();

            // Columnas fijas
            $fila[] = array('value' => (string)$numFila,   'style' => ExcelExporter::STYLE_INFO, 'force_string' => false);
            $fila[] = array('value' => $emp['nombre'],      'style' => ExcelExporter::STYLE_INFO);
            $fila[] = array('value' => $emp['rut'],         'style' => ExcelExporter::STYLE_INFO, 'force_string' => true);
            $fila[] = array('value' => $emp['dpto'],        'style' => ExcelExporter::STYLE_INFO);

            // Contadores por empleado
            $minutosTrab  = 0;
            $diasTrab     = 0;

            foreach ($diasColumna as $idxDia => $dia) {
                $dt      = new DateTime($dia);
                $dow     = (int)$dt->format('N');
                $esFinde = ($dow >= 6);

                if ($dia > $hoy) {
                    // Día futuro
                    $fila[] = array('value' => '—', 'style' => ExcelExporter::STYLE_FUTURO);

                } elseif (isset($marcasPorEmp[$rut][$dia])) {
                    $reg    = $marcasPorEmp[$rut][$dia];
                    $estado = $reg['estado'];
                    $horas  = $reg['total_horas'];
                    $mins   = hms_a_minutos($horas);

                    if ($estado === 'OK' || $estado === 'OBSERVADO') {
                        $display = ($horas ? substr($horas, 0, 5) : '—');
                        $fila[]  = array('value' => $display, 'style' => ExcelExporter::STYLE_OK);
                        $minutosTrab += $mins;
                        $diasTrab++;
                    } elseif ($estado === 'INCOMPLETO') {
                        $entStr  = $reg['entrada'] ? substr($reg['entrada'], 0, 5) : '?';
                        $display = 'INC ' . $entStr;
                        $fila[]  = array('value' => $display, 'style' => ExcelExporter::STYLE_TOTALES);
                        // Contamos el día pero no sumamos horas (incompleto)
                        $diasTrab++;
                    } elseif ($estado === 'ERROR') {
                        $fila[] = array('value' => 'ERR', 'style' => ExcelExporter::STYLE_FALTA);
                        $diasTrab++;
                    } else {
                        $display = ($horas ? substr($horas, 0, 5) : $estado);
                        $fila[]  = array('value' => $display, 'style' => ExcelExporter::STYLE_OK);
                        $minutosTrab += $mins;
                        $diasTrab++;
                    }

                } else {
                    // Sin marcación
                    if ($esFinde) {
                        // Fin de semana libre → descanso
                        $fila[] = array('value' => '—', 'style' => ExcelExporter::STYLE_FUTURO);
                    } else {
                        // Día hábil sin marcación → FALTÓ
                        $fila[] = array('value' => 'FALTÓ', 'style' => ExcelExporter::STYLE_FALTA);
                        $faltasPorDia[$idxDia]++;
                    }
                }
            }

            // Columnas de resumen
            $minutosEsperados  = $minutosEsperadosPorEmp;
            $minutosDiferencia = $minutosTrab - $minutosEsperados;

            $fila[] = array(
                'value' => (string)$diasTrab,
                'style' => ExcelExporter::STYLE_TOTALES,
            );
            $fila[] = array(
                'value' => minutos_a_hhmm_display($minutosTrab),
                'style' => ExcelExporter::STYLE_TOTALES,
            );
            $fila[] = array(
                'value' => minutos_a_hhmm_display($minutosEsperados),
                'style' => ExcelExporter::STYLE_TOTALES,
            );

            // Diferencia: verde si ≥ 0, rojo si < 0
            $signo     = $minutosDiferencia >= 0 ? '+' : '';
            $difStyle  = $minutosDiferencia >= 0
                ? ExcelExporter::STYLE_OK
                : ExcelExporter::STYLE_FALTA;

            $fila[] = array(
                'value' => $signo . minutos_a_hhmm_display(abs($minutosDiferencia)),
                'style' => $difStyle,
            );

            $xlsx->addRow($fila);

            // Acumular globales
            $totalGlobalDiasTrab  += $diasTrab;
            $totalGlobalMinutos   += $minutosTrab;
            $totalGlobalEsperados += $minutosEsperados;
            $numFila++;
        }
    }

    /* ── FILA DE TOTALES GLOBALES ───────────────────────────── */
    $fila = array();
    $fila[] = array('value' => 'TOTALES',        'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => '',               'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => '',               'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => count($infoEmp) . ' empleados', 'style' => ExcelExporter::STYLE_TOTALES);

    foreach ($diasColumna as $idxDia => $dia) {
        $dt      = new DateTime($dia);
        $esFinde = ((int)$dt->format('N') >= 6);
        if ($dia > $hoy || $esFinde) {
            $fila[] = array('value' => '—', 'style' => ExcelExporter::STYLE_FUTURO);
        } else {
            // Cuántos faltaron ese día
            $faltas = $faltasPorDia[$idxDia];
            $txt    = $faltas > 0 ? $faltas . ' falta' . ($faltas > 1 ? 's' : '') : '✓';
            $style  = $faltas > 0 ? ExcelExporter::STYLE_FALTA : ExcelExporter::STYLE_OK;
            $fila[] = array('value' => $txt, 'style' => $style);
        }
    }

    $globalDif      = $totalGlobalMinutos - $totalGlobalEsperados;
    $globalDifStyle = $globalDif >= 0 ? ExcelExporter::STYLE_OK : ExcelExporter::STYLE_FALTA;
    $globalSigno    = $globalDif >= 0 ? '+' : '';

    $fila[] = array('value' => (string)$totalGlobalDiasTrab,                              'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => minutos_a_hhmm_display($totalGlobalMinutos),               'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => minutos_a_hhmm_display($totalGlobalEsperados),             'style' => ExcelExporter::STYLE_TOTALES);
    $fila[] = array('value' => $globalSigno . minutos_a_hhmm_display(abs($globalDif)),    'style' => $globalDifStyle);
    $xlsx->addRow($fila);

    /* ── Ajuste de columnas y filas ──────────────────────────── */
    // autoFitColumns primero para calcular anchos de las columnas de días y resumen.
    // Min bajo (4.0) para que las columnas de día queden compactas.
    $xlsx->autoFitColumns(4.0, 50.0);
    $xlsx->autoFitRows();

    // Sobreescribir columnas fijas DESPUÉS del autofit:
    // autoFitColumns lee la fila del título ("REPORTE DE HORAS TRABAJADAS — …")
    // en la col 0 y la deja enorme. Forzamos anchos razonables para las 4 cols fijas.
    $xlsx->setColWidth(0,  5.0);   // N°          → angosto, solo 1-3 dígitos
    $xlsx->setColWidth(1, 32.0);   // Nombre      → texto largo de persona
    $xlsx->setColWidth(2, 13.0);   // RUT         → formato XX.XXX.XXX-X
    $xlsx->setColWidth(3, 22.0);   // Departamento→ texto medio

    // Columnas de resumen al final (índice = 4 + cantidad de días columna)
    $idxResumen = 4 + count($diasColumna);
    $xlsx->setColWidth($idxResumen,     11.0);  // Días Trabajados
    $xlsx->setColWidth($idxResumen + 1, 12.0);  // Total Horas
    $xlsx->setColWidth($idxResumen + 2, 13.0);  // Horas Esperadas
    $xlsx->setColWidth($idxResumen + 3, 13.0);  // Diferencia

    /* ── Enviar al navegador ─────────────────────────────────── */
    if (ob_get_length()) ob_end_clean();

    $xlsxData = $xlsx->generate();

    header('Content-Type: ' . $xlsx->getContentType());
    header('Content-Disposition: attachment; filename="' . $tituloArchivo . '"');
    header('Content-Length: ' . strlen($xlsxData));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $xlsxData;

} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error al generar el reporte: ' . $e->getMessage();
}