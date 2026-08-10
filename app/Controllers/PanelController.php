<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dashboard;
use App\Models\MarcacionResumen;

final class PanelController extends Controller
{
    private const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function index(): string
    {
        $mes = isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', trim((string) $_GET['mes']))
            ? trim((string) $_GET['mes']) : date('Y-m');

        $mesDate   = new \DateTime($mes . '-01');
        $mesLabel  = self::MESES[(int) $mesDate->format('n')] . ' ' . $mesDate->format('Y');
        $mesPrev   = (clone $mesDate)->modify('-1 month')->format('Y-m');
        $mesNext   = (clone $mesDate)->modify('+1 month')->format('Y-m');
        $diasEnMes = (int) $mesDate->format('t');

        $inicio = $mes . '-01';
        $fin    = (clone $mesDate)->modify('+1 month')->format('Y-m-01');

        $kpis = Dashboard::kpis($inicio, $fin);

        /* Series de marcas por día (rellenando los días sin datos). */
        $porDia = Dashboard::marcasPorDia($inicio, $fin);
        $serie  = [];
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha = $mes . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $serie[] = ['dia' => $d, 'fecha' => $fecha, 'total' => $porDia[$fecha] ?? 0];
        }
        $maxDia = max(array_column($serie, 'total') ?: [0]);

        /* Hoy (para presentes/ausentes), dentro o fuera del mes consultado. */
        $hoy = date('Y-m-d');

        $data = [
            'mes'           => $mes,
            'mesLabel'      => $mesLabel,
            'mesPrev'       => $mesPrev,
            'mesNext'       => $mesNext,
            'hoy'           => $hoy,
            'kpis'          => $kpis,
            'porDia'        => $serie,
            'maxDia'        => max(1, $maxDia),
            'porDpto'       => Dashboard::porDepartamento($inicio, $fin),
            'incidencias'   => Dashboard::ultimasIncidencias(8),
            'importaciones' => Dashboard::ultimasImportaciones(6),
            'presentes'     => MarcacionResumen::presentes($hoy, []),
            'ausentes'      => MarcacionResumen::ausentes($hoy, $inicio, $fin, []),
            'estados'       => [
                'ok' => $kpis['ok'],
                'obs' => $kpis['obs'],
                'inc' => $kpis['inc'],
                'err' => $kpis['err'],
            ],
        ];

        return $this->view('panel/index', [
            'title'     => 'Panel de control — Marcaciones',
            'activeNav' => 'inicio',
            'pageTitle' => 'Panel de control',
            'data'      => $data,
        ]);
    }
}
