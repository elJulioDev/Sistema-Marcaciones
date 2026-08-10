<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\MarcacionResumen;

final class CalendarioController extends Controller
{
    private const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    private const DIAS_C = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    private const MODOS = ['dia', 'semana', 'mes'];

    public function index(): string
    {
        $hoy    = date('Y-m-d');
        $mesDef = date('Y-m');
        $isJson = ($_GET['json'] ?? '') === '1';

        $mes = isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', trim((string) $_GET['mes']))
            ? trim((string) $_GET['mes']) : $mesDef;

        $modo = isset($_GET['modo']) && in_array($_GET['modo'], self::MODOS, true)
            ? (string) $_GET['modo'] : 'dia';

        $fechaSel = isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $_GET['fecha']))
            ? trim((string) $_GET['fecha'])
            : ($mes === $mesDef ? $hoy : $mes . '-01');
        if (substr($fechaSel, 0, 7) !== $mes) {
            $fechaSel = $mes . '-01';
        }

        $filtros = [
            'dpto'   => trim((string) ($_GET['dpto'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];

        /* ─── meta del mes ─────────────────────────────────────── */
        $mesDate   = new \DateTime($mes . '-01');
        $mesPrev   = (clone $mesDate)->modify('-1 month')->format('Y-m');
        $mesNext   = (clone $mesDate)->modify('+1 month')->format('Y-m');
        $mesLabel  = self::MESES[(int) $mesDate->format('n')] . ' ' . $mesDate->format('Y');
        $diasEnMes = (int) $mesDate->format('t');
        $primerDOW = (int) $mesDate->format('N');

        $mesInicioSQL = $mes . '-01';
        $mesFinSQL    = (clone $mesDate)->modify('+1 month')->format('Y-m-01');

        $diasHabilesDelMes = 0;
        $tmpDH = clone $mesDate;
        for ($i = 0; $i < $diasEnMes; $i++) {
            if ((int) $tmpDH->format('N') <= 5) {
                $diasHabilesDelMes++;
            }
            $tmpDH->modify('+1 day');
        }

        /* ─── datos ────────────────────────────────────────────── */
        $dots = MarcacionResumen::dotsPorMes($mesInicioSQL, $mesFinSQL, $filtros);

        $fechasSem = null;
        $selDate   = new \DateTime($fechaSel);
        if ($modo === 'semana') {
            $fechasSem = [];
            $lunes     = (clone $selDate)->modify('-' . ((int) $selDate->format('N') - 1) . ' days');
            for ($i = 0; $i < 7; $i++) {
                $fechasSem[] = (clone $lunes)->modify("+$i days")->format('Y-m-d');
            }
        }

        $stats = MarcacionResumen::stats($fechasSem, $mesInicioSQL, $mesFinSQL, $filtros);

        $presentes = MarcacionResumen::presentes($fechaSel, $filtros);
        $ausentes  = MarcacionResumen::ausentes($fechaSel, $mesInicioSQL, $mesFinSQL, $filtros);

        /* ─── franja semanal ───────────────────────────────────── */
        $selDOW = (int) $selDate->format('N');
        $lunes  = (clone $selDate)->modify('-' . ($selDOW - 1) . ' days');
        $semana = [];
        for ($i = 0; $i < 7; $i++) {
            $d     = (clone $lunes)->modify("+$i days");
            $fstr  = $d->format('Y-m-d');
            $dRaw  = $dots[$fstr] ?? null;
            $semana[] = [
                'fecha' => $fstr,
                'label' => self::DIAS_C[$i],
                'num'   => (int) $d->format('j'),
                'fin'   => $i >= 5,
                'hoy'   => $fstr === $hoy,
                'en_mes' => substr($fstr, 0, 7) === $mes,
                'dot'   => $dRaw,
            ];
        }
        $numSemana = (int) $lunes->format('W');

        /* ─── matrices ─────────────────────────────────────────── */
        $matrizEmps = $matrizData = $ausentesSemana = [];

        if ($modo === 'semana') {
            $fechasSem        = array_column($semana, 'fecha');
            $matriz           = MarcacionResumen::matrizSemana($fechasSem, $filtros);
            $matrizEmps       = $matriz['emps'];
            $matrizData       = $matriz['data'];
            $ausentesSemana   = MarcacionResumen::ausentesSemana($fechasSem, $mesInicioSQL, $mesFinSQL, $filtros);
        }

        $diasHabilesListaMes = $matrizEmpsMes = $matrizDataMes = $ausentesMes = [];

        if ($modo === 'mes') {
            $letrasDia = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
            $tmpDateML = clone $mesDate;
            for ($i = 0; $i < $diasEnMes; $i++) {
                $dow = (int) $tmpDateML->format('N');
                if ($dow <= 7) {
                    $diasHabilesListaMes[] = [
                        'fecha'   => $tmpDateML->format('Y-m-d'),
                        'label'   => $letrasDia[$dow - 1],
                        'num'     => (int) $tmpDateML->format('j'),
                        'hoy'     => $tmpDateML->format('Y-m-d') === $hoy,
                        'esHabil' => $dow <= 5,
                    ];
                }
                $tmpDateML->modify('+1 day');
            }

            $matrizMes     = MarcacionResumen::matrizMes($mesInicioSQL, $mesFinSQL, $filtros);
            $matrizEmpsMes = $matrizMes['emps'];
            $matrizDataMes = $matrizMes['data'];
            $ausentesMes   = $matrizMes['ausentes'];
        }

        $fechaDisplay = self::DIAS[(int) $selDate->format('N')] . ', '
            . $selDate->format('j') . ' de '
            . self::MESES[(int) $selDate->format('n')] . ' '
            . $selDate->format('Y');

        $data = [
            'mes'                  => $mes,
            'mesLabel'             => $mesLabel,
            'mesPrev'              => $mesPrev,
            'mesNext'              => $mesNext,
            'hoy'                  => $hoy,
            'fechaSel'             => $fechaSel,
            'modo'                 => $modo,
            'fechaDisplay'         => $fechaDisplay,
            'primerDOW'            => $primerDOW,
            'diasEnMes'            => $diasEnMes,
            'diasHabilesDelMes'    => $diasHabilesDelMes,
            'numSemana'            => $numSemana,
            'dots'                 => $dots,
            'stats'                => $stats,
            'presentes'            => $presentes,
            'ausentes'             => $ausentes,
            'semana'               => $semana,
            'matrizEmps'           => $matrizEmps,
            'matrizData'           => $matrizData,
            'ausentesSemana'       => $ausentesSemana,
            'diasHabilesListaMes'  => $diasHabilesListaMes,
            'matrizEmpsMes'        => $matrizEmpsMes,
            'matrizDataMes'        => $matrizDataMes,
            'ausentesMes'          => $ausentesMes,
            'filtros'              => $filtros,
            'dptos'                => MarcacionResumen::departamentos(),
        ];

        if ($isJson) {
            return $this->json($data);
        }

        return $this->view('calendario/index', [
            'title'     => 'Marcaciones — Calendario',
            'activeNav' => 'calendario',
            'data'      => $data,
        ], null);
    }
}
