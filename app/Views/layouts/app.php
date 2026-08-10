<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Sistema de Marcaciones') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('/assets/img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
</head>
<body>
<header class="app-header">
    <div class="container app-header-inner">
        <a class="app-brand" href="<?= base_url('/') ?>">Sistema de Marcaciones</a>
        <nav class="app-nav">
            <!-- Navegación real (navbar) se migra en la Fase 3 -->
        </nav>
    </div>
</header>

<main class="container app-main">
    <?= $content ?>
</main>

<footer class="app-footer">
    <div class="container">
        &copy; <?= date('Y') ?> Sistema de Marcaciones
    </div>
</footer>
</body>
</html>
