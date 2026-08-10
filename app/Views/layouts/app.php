<?php declare(strict_types=1);

use App\Core\Auth;
?>
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

        <?php if (Auth::check()): ?>
            <nav class="app-nav">
                <a href="<?= base_url('/') ?>" class="<?= ($activeNav ?? '') === 'inicio' ? 'active' : '' ?>">Inicio</a>
                <a href="<?= base_url('/calendario') ?>" class="<?= ($activeNav ?? '') === 'calendario' ? 'active' : '' ?>">Calendario</a>
                <a href="<?= base_url('/importar') ?>" class="<?= ($activeNav ?? '') === 'importar' ? 'active' : '' ?>">Importar</a>
                <a href="<?= base_url('/observaciones') ?>" class="<?= ($activeNav ?? '') === 'observaciones' ? 'active' : '' ?>">Observaciones</a>
                <a href="<?= base_url('/consulta') ?>" class="<?= ($activeNav ?? '') === 'consulta' ? 'active' : '' ?>">Consulta</a>
            </nav>

            <div class="app-user">
                <span class="app-user-name">Hola, <?= h(Auth::nombre() ?? '') ?></span>
                <span class="app-user-rol">(<?= h(Auth::rol() ?? '') ?>)</span>
                <a class="btn btn-ghost" href="<?= base_url('/logout') ?>">Salir</a>
            </div>
        <?php endif; ?>
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
