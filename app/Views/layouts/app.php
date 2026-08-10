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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
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
                if (localStorage.getItem('sm-sidebar-collapsed') === '1') {
                    document.documentElement.setAttribute('data-sidebar-collapsed', '1');
                }
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
    <?php foreach (($styles ?? []) as $style): ?>
        <link rel="stylesheet" href="<?= base_url($style) ?>">
    <?php endforeach; ?>
</head>
<body>
<?= View::partial('layouts/partials/chrome', [
    'content'      => $content,
    'activeNav'    => $activeNav ?? '',
    'pageTitle'    => $pageTitle ?? '',
    'contentClass' => 'container' . (!empty($wide) ? ' wide' : ''),
]) ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('/assets/js/app.js') ?>" defer></script>
<?php foreach (($scripts ?? []) as $script): ?>
    <script src="<?= base_url($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
