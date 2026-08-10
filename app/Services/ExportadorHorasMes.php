<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Reporte XLSX de horas trabajadas por empleado en un mes.
 *
 * Estructura:
 *   Columnas fijas : N° | Nombre | RUT | Departamento
 *   Columnas días  : una por día del mes; sábados/domingos solo si hubo
 *                    marcación ese día.
 *   Columnas resumen: días trabajados, total horas, horas esperadas y
 *                    diferencia (total − esperado).
 *
 * Colores por estado: OK/OBSERVADO verde, INCOMPLETO ámbar, ERROR/FALTÓ
 * rojo, fin de semana/futuro gris, resúmenes ámbar.
 */
final class ExportadorHorasMes
{
    private const DIAS_ABREV   = ['', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
    private const MESES_ABREV  = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                                  'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    private const NOMBRES_MES  = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
                                  '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
                                  '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
                                  '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];

    /**
     * Genera el reporte y devuelve binario, nombre de archivo y tipo MIME.
     *
     * @return array{data:string, nombre:string, tipo:string}
     */
    public function generar(string $mes = '', string $dpto = '', string $q = ''): array
    {
        $pdo = Database::pdo();

        $mes  = $mes  !== '' ? $mes  : date('Y-m');
        $dpto = trim($dpto);
        $q    = trim($q);

        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }

        $inicioMes = new \DateTime($mes . '-01');
        $finMes    = clone $inicioMes;
        $finMes->modify('last day of this month');

        $hoy = date('Y-m-d');

        $partesMes  = explode('-', $mes);
        $nombreMes  = self::NOMBRES_MES[$partesMes[1]] . ' ' . $partesMes[0];
        $tituloPeriodo = 'REPORTE DE HORAS TRABAJADAS — ' . strtoupper($nombreMes);
        $sufijoDpto    = $dpto !== '' ? '_' . preg_replace('/[^a-zA-Z0-9]/', '', $dpto) : '';
        $tituloArchivo = 'horas_' . str_replace('-', '_', $mes) . $sufijoDpto . '.xlsx';

        // ── 1. Todos los días del mes ────────────────────────────────
        $todosDias = [];
        $finMesClon = clone $finMes;
        $finMesClon->modify('+1 day');
        $periodo   = new \DatePeriod($inicioMes, new \DateInterval('P1D'), $finMesClon);
        foreach ($periodo as $dt) {
            $todosDias[] = $dt->format('Y-m-d');
        }

        // ── 2. ¿Qué fines de semana tienen marcaciones? ──────────────
        $ph      = implode(',', array_fill(0, count($todosDias), '?'));
        $params  = $todosDias;
        $stmt    = $pdo->prepare(
            "SELECT DISTINCT fecha FROM marcaciones_resumen WHERE fecha IN ($ph)"
        );
        $stmt->execute($params);
        $diasConMarcas = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        // ── 3. Filtrar días a incluir como columnas ──────────────────
        $diasColumna = [];
        foreach ($todosDias as $dia) {
            $dtAux   = new \DateTime($dia);
            $dow     = (int)$dtAux->format('N'); // 1=Lun, 7=Dom
            $esFinde = ($dow >= 6);
            if (!$esFinde || isset($diasConMarcas[$dia])) {
                $diasColumna[] = $dia;
            }
        }

        // ── 4. Consultar marcaciones_resumen del mes ─────────────────
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

        // ── 5. Construir estructura indexada: [rut][fecha] ───────────
        $marcasPorEmp = [];  // [rut_base][fecha] = registro
        $infoEmp      = [];  // [rut_base] = {nombre, dpto, numero}

        foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rut = $r['rut_base'];
            $marcasPorEmp[$rut][$r['fecha']] = $r;
            if (!isset($infoEmp[$rut])) {
                $infoEmp[$rut] = [
                    'nombre' => $r['nombre'],
                    'dpto'   => $r['dpto'],
                    'numero' => $r['numero'],
                    'rut'    => $rut,
                ];
            }
        }

        uasort($infoEmp, static fn(array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));

        // ── 6. Calcular días hábiles pasados (horas esperadas) ───────
        $diasHabilesPasados = 0;
        foreach ($diasColumna as $dia) {
            $dtAux   = new \DateTime($dia);
            $dow     = (int)$dtAux->format('N');
            $esFinde = ($dow >= 6);
            if (!$esFinde && $dia <= $hoy) {
                $diasHabilesPasados++;
            }
        }

        // ── 7. Crear exportador ──────────────────────────────────────
        $xlsx = ExcelExporter::create();

        $totalCols = 4 + count($diasColumna);

        $fila = [];
        $fila[] = [
            'value'   => $tituloPeriodo,
            'style'   => ExcelExporter::STYLE_HEADER,
            'colspan' => $totalCols,
        ];
        for ($i = 1; $i < $totalCols; $i++) {
            $fila[] = ['value' => '', 'style' => ExcelExporter::STYLE_HEADER];
        }
        $xlsx->addRow($fila);

        $filtroInfo = 'Todos los departamentos';
        if ($dpto !== '') {
            $filtroInfo = 'Depto: ' . $dpto;
        }
        if ($q !== '') {
            $filtroInfo .= ($dpto !== '' ? ' | ' : '') . 'Búsqueda: "' . $q . '"';
        }

        $meta  = 'Generado: ' . date('d/m/Y H:i');
        $meta .= '   |   Período: ' . $inicioMes->format('d/m/Y') . ' al ' . $finMes->format('d/m/Y');
        $meta .= '   |   Días hábiles pasados: ' . $diasHabilesPasados;
        $meta .= '   |   ' . $filtroInfo;

        $fila = [];
        $fila[] = [
            'value'   => $meta,
            'style'   => ExcelExporter::STYLE_INFO,
            'colspan' => $totalCols,
        ];
        for ($i = 1; $i < $totalCols; $i++) {
            $fila[] = ['value' => '', 'style' => ExcelExporter::STYLE_INFO];
        }
        $xlsx->addRow($fila);

        $fila = [];
        $fila[] = ['value' => 'N°',           'style' => ExcelExporter::STYLE_HEADER];
        $fila[] = ['value' => 'Nombre',        'style' => ExcelExporter::STYLE_HEADER];
        $fila[] = ['value' => 'RUT',           'style' => ExcelExporter::STYLE_HEADER];
        $fila[] = ['value' => 'Departamento',  'style' => ExcelExporter::STYLE_HEADER];

        foreach ($diasColumna as $dia) {
            $dt    = new \DateTime($dia);
            $dow   = (int)$dt->format('N');
            $label = self::DIAS_ABREV[$dow] . "\n" . (int)$dt->format('j') . '/' . self::MESES_ABREV[(int)$dt->format('n')];
            $fila[] = ['value' => $label, 'style' => ExcelExporter::STYLE_DATE_HEADER];
        }

        $xlsx->addRow($fila);

        // ── FILAS DE DATOS ───────────────────────────────────────────
        $faltasPorDia = array_fill(0, count($diasColumna), 0);
        $numFila      = 1;

        if ($infoEmp === []) {
            $fila = [];
            $fila[] = [
                'value' => 'Sin registros para el período y filtros seleccionados.',
                'style' => ExcelExporter::STYLE_FUTURO,
            ];
            for ($i = 1; $i < $totalCols; $i++) {
                $fila[] = ['value' => '', 'style' => ExcelExporter::STYLE_FUTURO];
            }
            $xlsx->addRow($fila);
        } else {
            foreach ($infoEmp as $rut => $emp) {
                $fila = [];

                $fila[] = ['value' => (string)$numFila, 'style' => ExcelExporter::STYLE_INFO, 'force_string' => false];
                $fila[] = ['value' => $emp['nombre'],   'style' => ExcelExporter::STYLE_INFO];
                $fila[] = ['value' => $emp['rut'],      'style' => ExcelExporter::STYLE_INFO, 'force_string' => true];
                $fila[] = ['value' => $emp['dpto'],     'style' => ExcelExporter::STYLE_INFO];

                $minutosTrab = 0;
                $diasTrab    = 0;

                foreach ($diasColumna as $idxDia => $dia) {
                    $dt      = new \DateTime($dia);
                    $dow     = (int)$dt->format('N');
                    $esFinde = ($dow >= 6);

                    if ($dia > $hoy) {
                        $fila[] = ['value' => '—', 'style' => ExcelExporter::STYLE_FUTURO];
                    } elseif (isset($marcasPorEmp[$rut][$dia])) {
                        $reg    = $marcasPorEmp[$rut][$dia];
                        $estado = $reg['estado'];
                        $horas  = $reg['total_horas'];
                        $mins   = hms_a_minutos($horas);

                        if ($estado === 'OK' || $estado === 'OBSERVADO') {
                            $display = ($horas ? substr($horas, 0, 5) : '—');
                            $fila[]  = ['value' => $display, 'style' => ExcelExporter::STYLE_OK];
                            $minutosTrab += $mins;
                            $diasTrab++;
                        } elseif ($estado === 'INCOMPLETO') {
                            $entStr  = $reg['entrada'] ? substr($reg['entrada'], 0, 5) : '?';
                            $display = 'INC ' . $entStr;
                            $fila[]  = ['value' => $display, 'style' => ExcelExporter::STYLE_TOTALES];
                            $diasTrab++;
                        } elseif ($estado === 'ERROR') {
                            $fila[] = ['value' => 'ERR', 'style' => ExcelExporter::STYLE_FALTA];
                            $diasTrab++;
                        } else {
                            $display = ($horas ? substr($horas, 0, 5) : $estado);
                            $fila[]  = ['value' => $display, 'style' => ExcelExporter::STYLE_OK];
                            $minutosTrab += $mins;
                            $diasTrab++;
                        }
                    } else {
                        if ($esFinde) {
                            $fila[] = ['value' => '—', 'style' => ExcelExporter::STYLE_FUTURO];
                        } else {
                            $fila[] = ['value' => 'FALTÓ', 'style' => ExcelExporter::STYLE_FALTA];
                            $faltasPorDia[$idxDia]++;
                        }
                    }
                }

                $xlsx->addRow($fila);
                $numFila++;
            }
        }

        // ── FILA DE TOTALES GLOBALES ─────────────────────────────────
        $fila = [];
        $fila[] = ['value' => 'TOTALES',               'style' => ExcelExporter::STYLE_TOTALES];
        $fila[] = ['value' => '',                      'style' => ExcelExporter::STYLE_TOTALES];
        $fila[] = ['value' => '',                      'style' => ExcelExporter::STYLE_TOTALES];
        $fila[] = ['value' => count($infoEmp) . ' empleados', 'style' => ExcelExporter::STYLE_TOTALES];

        foreach ($diasColumna as $idxDia => $dia) {
            $dt      = new \DateTime($dia);
            $esFinde = ((int)$dt->format('N') >= 6);
            if ($dia > $hoy || $esFinde) {
                $fila[] = ['value' => '—', 'style' => ExcelExporter::STYLE_FUTURO];
            } else {
                $faltas = $faltasPorDia[$idxDia];
                $txt    = $faltas > 0 ? $faltas . ' falta' . ($faltas > 1 ? 's' : '') : '✓';
                $style  = $faltas > 0 ? ExcelExporter::STYLE_FALTA : ExcelExporter::STYLE_OK;
                $fila[] = ['value' => $txt, 'style' => $style];
            }
        }

        $xlsx->addRow($fila);

        // ── Ajuste de columnas y filas ───────────────────────────────
        $xlsx->autoFitColumns(4.0, 50.0);
        $xlsx->autoFitRows();

        $xlsx->setColWidth(0, 5.0);
        $xlsx->setColWidth(1, 32.0);
        $xlsx->setColWidth(2, 13.0);
        $xlsx->setColWidth(3, 22.0);

        return [
            'data'   => $xlsx->generate(),
            'nombre' => $tituloArchivo,
            'tipo'   => $xlsx->getContentType(),
        ];
    }
}
