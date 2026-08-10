<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Iniciar sesión') ?> | Sistema de Marcaciones</title>
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
<div class="dropdown auth-theme-toggle">
    <button type="button" class="btn-icon dropdown-toggle" data-bs-toggle="dropdown" aria-label="Cambiar tema" aria-expanded="false">
        <i class="bi bi-circle-half" id="themeIcon"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><h6 class="dropdown-header"><i class="bi bi-palette me-1"></i>Tema de la interfaz</h6></li>
        <li><button type="button" class="dropdown-item" data-theme-option="light" data-theme-label="Claro"><i class="bi bi-sun me-2"></i>Claro</button></li>
        <li><button type="button" class="dropdown-item" data-theme-option="dark" data-theme-label="Oscuro"><i class="bi bi-moon me-2"></i>Oscuro</button></li>
        <li><button type="button" class="dropdown-item" data-theme-option="auto" data-theme-label="Auto"><i class="bi bi-circle-half me-2"></i>Auto (sistema)</button></li>
    </ul>
</div>

<main class="auth-wrap">
    <div class="card auth-card">

        <div class="auth-brand">
            <span class="auth-logo" aria-hidden="true">
                <i class="bi bi-clock-history"></i>
            </span>
            <h1>Sistema de Marcaciones</h1>
            <p class="muted">Acceso para gestión de Recursos Humanos</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('/login') ?>" autocomplete="off">
            <div class="form-group">
                <label for="rut">RUT Funcionario</label>
                <input class="form-control" id="rut" type="text" name="rut"
                       placeholder="12345678-9" required value="<?= h($rut) ?>">
            </div>

            <div class="form-group">
                <label for="clave">Contraseña</label>
                <input class="form-control" id="clave" type="password" name="clave"
                       placeholder="••••••••" required>
            </div>

            <button class="btn btn-primary btn-block" type="submit">
                <i class="bi bi-box-arrow-in-right"></i> Ingresar al Sistema
            </button>
        </form>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('/assets/js/app.js') ?>" defer></script>
</body>
</html>
