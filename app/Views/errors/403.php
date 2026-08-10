<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Acceso denegado') ?> | Sistema de Marcaciones</title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('/assets/img/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sm-theme') || 'auto';
                var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var applied = (t === 'auto') ? (dark ? 'dark' : 'light') : t;
                document.documentElement.setAttribute('data-theme', applied);
                document.documentElement.setAttribute('data-bs-theme', applied);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
</head>
<body class="auth-page">
<main class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-brand">
            <span class="auth-logo" aria-hidden="true">
                <i class="bi bi-shield-lock"></i>
            </span>
            <h1>403 — Acceso denegado</h1>
            <p class="muted">Tu rol de usuario no tiene permisos para acceder a esta sección.</p>
        </div>
        <a class="btn btn-primary btn-block" href="<?= base_url('/dashboard') ?>">
            <i class="bi bi-house-door"></i> Volver al inicio
        </a>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
