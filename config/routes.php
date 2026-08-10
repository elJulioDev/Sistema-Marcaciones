<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;

// Públicas (auth: null)
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);

// Protegidas: cualquier usuario con sesión iniciada
$router->get('/', [HomeController::class, 'index'], 'login');
$router->get('/logout', [AuthController::class, 'logout'], 'login');
