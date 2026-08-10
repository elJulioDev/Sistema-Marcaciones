<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CalendarioController;
use App\Controllers\ConsultaController;
use App\Controllers\ImportacionController;
use App\Controllers\MarcacionController;
use App\Controllers\MesController;
use App\Controllers\ObservacionesController;
use App\Controllers\PanelController;

// Públicas (auth: null)
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);

// Protegidas: cualquier usuario con sesión iniciada
$router->get('/', [PanelController::class, 'index'], 'login');
$router->get('/logout', [AuthController::class, 'logout'], 'login');

$router->get('/consulta', [ConsultaController::class, 'index'], 'login');

$router->get('/calendario', [CalendarioController::class, 'index'], 'login');

$router->get('/observaciones', [ObservacionesController::class, 'index'], 'login');

$router->get('/importar', [ImportacionController::class, 'index'], 'login');
$router->post('/importar/importar', [ImportacionController::class, 'importar'], 'login');

$router->get('/marcacion/editar', [MarcacionController::class, 'editar'], 'login');
$router->post('/marcacion/editar', [MarcacionController::class, 'editar'], 'login');

// Solo admin
$router->get('/eliminar-mes', [MesController::class, 'index'], 'admin');
$router->post('/eliminar-mes', [MesController::class, 'eliminar'], 'admin');
