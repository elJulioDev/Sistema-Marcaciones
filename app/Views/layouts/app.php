<?php

declare(strict_types=1);

use App\Core\View;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Sistema de Marcaciones') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('/assets/img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
    <?php foreach (($styles ?? []) as $style): ?>
        <link rel="stylesheet" href="<?= base_url($style) ?>">
    <?php endforeach; ?>
</head>
<body>
<?= View::partial('layouts/partials/navbar', ['activeNav' => $activeNav ?? '']) ?>

<main class="container app-main<?= !empty($wide) ? ' wide' : '' ?>">
    <?= $content ?>
</main>

<footer class="app-footer">
    <div class="container">
        &copy; <?= date('Y') ?> Sistema de Marcaciones
    </div>
</footer>
<?php foreach (($scripts ?? []) as $script): ?>
    <script src="<?= base_url($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
