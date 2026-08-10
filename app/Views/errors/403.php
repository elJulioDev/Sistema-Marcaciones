<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Acceso denegado') ?> | Sistema de Marcaciones</title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('/assets/img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
</head>
<body class="auth-page">
<main class="auth-wrap">
    <div class="card auth-card">
        <h1>403 — Acceso denegado</h1>
        <p class="muted">Tu rol de usuario no tiene permisos para acceder a esta sección.</p>
        <a class="btn btn-primary btn-block" href="<?= base_url('/') ?>">Volver al inicio</a>
    </div>
</main>
</body>
</html>
