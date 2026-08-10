<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Reporte XLSX de inasistencias para un período (semana o mes).
 *
 * LÓGICA DE COLUMNAS DE FIN DE SEMANA:
 *   - Lunes a Viernes: siempre se incluyen como columnas.
 *   - Sábado y Domingo: solo se incluyen si existe AL MENOS UN registro
 *     de marcación en esa fecha dentro del período seleccionado.
 *
 * LÓGICA DE EMPLEADOS:
 *   - Solo se listan empleados que tienen al menos un registro dentro de
 *     los días efectivamente incluidos. Nunca se consulta el mes anterior
 *     ni períodos ajenos a la selección actual.
 */
final class ExportadorInasistencias
{
    /**
     * Genera el reporte y devuelve binario, nombre de archivo y tipo MIME.
     *
     * @return array{data:string, nombre:string, tipo:string}
     */
    public function generar(string $rango = 'semana', string $mes = '', string $fecha = ''): array
    {
        $pdo = Database::pdo();

        $rango    = $rango !== '' ? $rango : 'semana';
        $mes      = $mes   !== '' ? $mes   : date('Y-m');
        $fecha    = $fecha !== '' ? $fecha : date('Y-m-d');

        $xlsx = ExcelExporter::create();

        // ── 1. Determinar fechas candidatas del período ───────────────
        $fechasCandidatas = [];

        if ($rango === 'semana') {
            $selDate = new \DateTime($fecha);
            $selDOW  = (int)$selDate->format('N');

            $lunes = clone $selDate;
            $lunes->modify('-' . ($selDOW - 1) . ' days');
            $numSemana = (int)$lunes->format('W');

            for ($i = 0; $i < 7; $i++) {
                $d = clone $lunes;
                $d->modify("+$i days");
                $fechasCandidatas[] = $d->format('Y-m-d');
            }
        } else {
            $inicioMes = new \DateTime($mes . '-01');
            $finMes    = new \DateTime($mes . '-01');
            $finMes->modify('last day of this month');
            $finMes->modify('+1 day');

            $periodo = new \DatePeriod($inicioMes, new \DateInterval('P1D'), $finMes);
            foreach ($periodo as $dt) {
                $fechasCandidatas[] = $dt->format('Y-m-d');
            }
        }

        // ── 2. Consultar qué días candidatos tienen al menos 1 marcación ──
        $diasConMarcas = [];
        if ($fechasCandidatas !== []) {
            $ph     = implode(',', array_fill(0, count($fechasCandidatas), '?'));
            $stmtF  = $pdo->prepare(
                "SELECT DISTINCT fecha FROM marcaciones_resumen WHERE fecha IN ($ph)"
            );
            $stmtF->execute($fechasCandidatas);
            foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $diasConMarcas[$r['fecha']] = true;
            }
        }

        // ── 3. Filtrar candidatas ────────────────────────────────────
        $diasRevisar = [];
        foreach ($fechasCandidatas as $f) {
            $esFinde = (date('N', strtotime($f)) >= 6);
            if (!$esFinde || isset($diasConMarcas[$f])) {
                $diasRevisar[] = $f;
            }
        }

        // ── 4. Títulos dinámicos ─────────────────────────────────────
        $numSemana = 0;
        if ($rango === 'semana') {
            $numSemana = (int)$lunes->format('W');

            $primerDia = new \DateTime($diasRevisar[0]);
            $ultimoDia = new \DateTime(end($diasRevisar));

            $tituloArchivo = 'Reporte_Sem' . $numSemana
                           . '_' . $primerDia->format('d-m-Y')
                           . '_al_' . $ultimoDia->format('d-m-Y') . $xlsx->getExtension();

            $tituloPeriodo = 'Semana ' . $numSemana
                           . ' — del ' . $primerDia->format('d/m/Y')
                           . ' al '    . $ultimoDia->format('d/m/Y');
        } else {
            $mesesNombres = [
                1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
            ];
            [$anio, $indiceMes] = explode('-', $mes);
            $nombreMes = $mesesNombres[(int)$indiceMes];

            $tituloArchivo = 'Inasistencias_' . $nombreMes . '_' . $anio . $xlsx->getExtension();
            $tituloPeriodo = $nombreMes . ' ' . $anio;
        }

        // ── 5. Consultar empleados ───────────────────────────────────
        // Solo empleados con registros exactamente en los días de $diasRevisar.
        $empleados = [];
        if ($diasRevisar !== []) {
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

        // ── 6. Consultar marcaciones del período ─────────────────────
        $marcas = [];
        if ($diasRevisar !== []) {
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

        // ── 7. Construir filas de datos ─────────────────────────────
        // Un empleado se incluye si: (a) faltó al menos un día hábil, o
        // (b) trabajó al menos un fin de semana incluido en $diasRevisar.
        $hoy          = date('Y-m-d');
        $faltasPorDia = array_fill(0, count($diasRevisar), 0);
        $filasDatos   = [];

        foreach ($empleados as $emp) {
            $rut            = $emp['rut_base'];
            $tuvoFalta      = false;
            $tuvoMarcaFinde = false;
            $celdas         = [];

            foreach ($diasRevisar as $idxDia => $dia) {
                $esFinde = (date('N', strtotime($dia)) >= 6);

                if ($dia > $hoy) {
                    $celdas[] = ['value' => '—', 'style' => ExcelExporter::STYLE_FUTURO];
                } elseif (isset($marcas[$rut][$dia])) {
                    $celdas[] = ['value' => '✓ OK', 'style' => ExcelExporter::STYLE_OK];
                    if ($esFinde) {
                        $tuvoMarcaFinde = true;
                    }
                } else {
                    if ($esFinde) {
                        $celdas[] = ['value' => '—', 'style' => ExcelExporter::STYLE_FUTURO];
                    } else {
                        $celdas[] = ['value' => 'FALTÓ', 'style' => ExcelExporter::STYLE_FALTA];
                        $faltasPorDia[$idxDia]++;
                        $tuvoFalta = true;
                    }
                }
            }

            if ($tuvoFalta || $tuvoMarcaFinde) {
                $filasDatos[] = [
                    'emp'    => $emp,
                    'celdas' => $celdas,
                ];
            }
        }

        // ── Construir el XLSX ────────────────────────────────────────
        $totalCols = 4 + count($diasRevisar);

        $filaTitle   = [];
        $filaTitle[] = [
            'value'   => 'REPORTE DE INASISTENCIAS — ' . strtoupper($tituloPeriodo),
            'style'   => ExcelExporter::STYLE_HEADER,
            'colspan' => $totalCols,
        ];
        for ($i = 1; $i < $totalCols; $i++) {
            $filaTitle[] = ['value' => '', 'style' => ExcelExporter::STYLE_HEADER];
        }
        $xlsx->addRow($filaTitle);

        $fechaGen  = date('d/m/Y H:i');
        $filaSub   = [];
        $filaSub[] = [
            'value'   => 'Generado el ' . $fechaGen . '   |   Total empleados listados: ' . count($filasDatos),
            'style'   => ExcelExporter::STYLE_INFO,
            'colspan' => $totalCols,
        ];
        for ($i = 1; $i < $totalCols; $i++) {
            $filaSub[] = ['value' => '', 'style' => ExcelExporter::STYLE_INFO];
        }
        $xlsx->addRow($filaSub);

        $filaCab   = [];
        $filaCab[] = ['value' => 'Nombre Funcionario', 'style' => ExcelExporter::STYLE_HEADER];
        $filaCab[] = ['value' => 'RUT',                'style' => ExcelExporter::STYLE_HEADER];
        $filaCab[] = ['value' => 'Departamento',       'style' => ExcelExporter::STYLE_HEADER];
        $filaCab[] = ['value' => 'N° Empleado',        'style' => ExcelExporter::STYLE_HEADER];

        $diasSemana = ['', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
        foreach ($diasRevisar as $dia) {
            $dt      = new \DateTime($dia);
            $label   = $diasSemana[(int)$dt->format('N')] . "\n" . $dt->format('d/m');
            $filaCab[] = ['value' => $label, 'style' => ExcelExporter::STYLE_DATE_HEADER];
        }
        $xlsx->addRow($filaCab);

        if ($filasDatos === []) {
            $filaVacia   = [];
            $filaVacia[] = [
                'value' => '✓  No se registraron inasistencias ni marcas de fin de semana en este período.',
                'style' => ExcelExporter::STYLE_OK,
            ];
            for ($i = 1; $i < $totalCols; $i++) {
                $filaVacia[] = ['value' => '', 'style' => ExcelExporter::STYLE_OK];
            }
            $xlsx->addRow($filaVacia);
        } else {
            foreach ($filasDatos as $fd) {
                $emp  = $fd['emp'];
                $fila = [];
                $fila[] = ['value' => $emp['nombre'],   'style' => ExcelExporter::STYLE_INFO];
                $fila[] = ['value' => $emp['rut_base'], 'style' => ExcelExporter::STYLE_INFO, 'force_string' => true];
                $fila[] = ['value' => $emp['dpto'],     'style' => ExcelExporter::STYLE_INFO];
                $fila[] = ['value' => $emp['numero'],   'style' => ExcelExporter::STYLE_INFO, 'force_string' => true];

                foreach ($fd['celdas'] as $celda) {
                    $fila[] = $celda;
                }
                $xlsx->addRow($fila);
            }
        }

        $filaTot   = [];
        $filaTot[] = ['value' => 'TOTAL INASISTENCIAS POR DÍA', 'style' => ExcelExporter::STYLE_TOTALES];
        $filaTot[] = ['value' => '', 'style' => ExcelExporter::STYLE_TOTALES];
        $filaTot[] = ['value' => '', 'style' => ExcelExporter::STYLE_TOTALES];
        $filaTot[] = ['value' => '', 'style' => ExcelExporter::STYLE_TOTALES];

        foreach ($diasRevisar as $idxDia => $dia) {
            $cnt     = $faltasPorDia[$idxDia];
            $esFinde = (date('N', strtotime($dia)) >= 6);
            if ($dia > $hoy || $esFinde) {
                $filaTot[] = ['value' => '—', 'style' => ExcelExporter::STYLE_FUTURO];
            } else {
                $filaTot[] = ['value' => (string)$cnt, 'style' => ExcelExporter::STYLE_TOTALES];
            }
        }
        $xlsx->addRow($filaTot);

        $xlsx->autoFitColumns(10.0, 55.0);
        $xlsx->autoFitRows();

        $data = $xlsx->generate();

        return [
            'data'   => $data,
            'nombre' => $tituloArchivo,
            'tipo'   => $xlsx->getContentType(),
        ];
    }
}
