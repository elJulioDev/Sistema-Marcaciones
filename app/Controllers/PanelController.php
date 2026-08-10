<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class PanelController extends Controller
{
    public function index(): string
    {
        return $this->view('panel/index', [
            'title'     => 'Panel Principal - Marcaciones',
            'activeNav' => 'inicio',
        ]);
    }
}
