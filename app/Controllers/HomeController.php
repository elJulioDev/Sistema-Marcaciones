<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * Página de inicio provisional. Se reemplaza por el Panel real en la
 * Fase 4. Hoy demuestra que la infraestructura (env, DB, router, layout,
 * auth, assets) funciona.
 */
final class HomeController extends Controller
{
    public function index(): string
    {
        $totalResumenes = (int) Database::pdo()
            ->query('SELECT COUNT(*) FROM marcaciones_resumen')
            ->fetchColumn();

        return $this->view('home/index', [
            'title'          => 'Panel de control',
            'activeNav'      => 'inicio',
            'totalResumenes' => $totalResumenes,
        ]);
    }
}
