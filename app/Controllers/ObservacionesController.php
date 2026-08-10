<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\MarcacionResumen;

final class ObservacionesController extends Controller
{
    private const REGISTROS_POR_PAGINA = 50;

    public function index(): string
    {
        $filtroEstado = trim((string) ($_GET['estado'] ?? ''));
        $q            = trim((string) ($_GET['q'] ?? ''));
        $periodo      = trim((string) ($_GET['periodo'] ?? ''));

        $filtros = ['filtro_estado' => $filtroEstado, 'q' => $q, 'periodo' => $periodo];

        $totalRegistros = MarcacionResumen::listado($filtros, self::REGISTROS_POR_PAGINA, 0)['total'];

        $totalPaginas   = max(1, (int) ceil($totalRegistros / self::REGISTROS_POR_PAGINA));
        $paginaActual   = isset($_GET['p']) && is_numeric($_GET['p'])
            ? max(1, min((int) $_GET['p'], $totalPaginas))
            : 1;
        $offset         = ($paginaActual - 1) * self::REGISTROS_POR_PAGINA;

        $resultado = MarcacionResumen::listado($filtros, self::REGISTROS_POR_PAGINA, $offset);

        return $this->view('observaciones/index', [
            'title'                => 'Observaciones de marcaciones',
            'activeNav'            => 'observaciones',
            'styles'               => ['/assets/css/modulos.css', '/assets/css/observaciones.css'],
            'filtroEstado'         => $filtroEstado,
            'q'                    => $q,
            'periodo'              => $periodo,
            'rows'                 => $resultado['rows'],
            'paginaActual'         => $paginaActual,
            'totalPaginas'         => $totalPaginas,
            'totalRegistros'       => $resultado['total'],
            'registrosPorPagina'   => self::REGISTROS_POR_PAGINA,
            'offset'               => $offset,
        ]);
    }
}
