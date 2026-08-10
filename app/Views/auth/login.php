<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Iniciar sesión') ?> | Sistema de Marcaciones</title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('/assets/img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
</head>
<body class="auth-page">
<main class="auth-wrap">
    <div class="card auth-card">

        <div class="auth-brand">
            <span class="auth-logo" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
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

            <button class="btn btn-primary btn-block" type="submit">Ingresar al Sistema</button>
        </form>

    </div>
</main>
</body>
</html>
