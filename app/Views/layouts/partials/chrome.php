<?php

declare(strict_types=1);

use App\Core\Auth;

$active      = $activeNav ?? '';
$pageTitle   = $pageTitle ?? '';
$content     = $content ?? '';
$contentClass = $contentClass ?? '';

$titulos = [
    'inicio'        => 'Panel de control',
    'calendario'    => 'Calendario de marcaciones',
    'importar'      => 'Importar marcaciones',
    'observaciones' => 'Observaciones e incidencias',
    'consulta'      => 'Consulta por funcionario',
    'eliminar-mes'  => 'Administración — Eliminar mes',
];

if ($pageTitle === '') {
    $pageTitle = $titulos[$active] ?? 'Sistema de Marcaciones';
}

$nombre = (string) (Auth::nombre() ?? '');
$rol    = (string) (Auth::rol() ?? '');
$inicial = $nombre !== '' ? mb_strtoupper(mb_substr($nombre, 0, 1)) : 'U';

$rolLabels = [
    'admin'    => 'Administrador',
    'operador' => 'Operador',
];
$rolLabel = $rolLabels[$rol] ?? $rol;

$nav = [
    'General' => [
        ['ruta' => '/',            'nav' => 'inicio',        'icono' => 'bi-house-door',   'label' => 'Inicio'],
        ['ruta' => '/calendario',  'nav' => 'calendario',    'icono' => 'bi-calendar3',    'label' => 'Calendario'],
    ],
    'Asistencia' => [
        ['ruta' => '/importar',    'nav' => 'importar',      'icono' => 'bi-cloud-arrow-up', 'label' => 'Importar'],
        ['ruta' => '/observaciones','nav' => 'observaciones', 'icono' => 'bi-exclamation-triangle', 'label' => 'Observaciones'],
        ['ruta' => '/consulta',    'nav' => 'consulta',      'icono' => 'bi-search',       'label' => 'Consulta'],
    ],
];

$navAdmin = [
    ['ruta' => '/eliminar-mes', 'nav' => 'eliminar-mes', 'icono' => 'bi-trash', 'label' => 'Eliminar mes'],
];
?>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-header-row">
            <a class="sidebar-brand" href="<?= base_url('/') ?>" title="Sistema de Marcaciones">
                <span class="sidebar-brand-icon"><i class="bi bi-clock-history"></i></span>
                <span class="sidebar-brand-name">Marcaciones</span>
            </a>
            <button type="button" class="btn-sidebar-close" id="btnSidebarClose" aria-label="Cerrar menú">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <hr>
    </div>

    <nav class="sidebar-nav" id="sidebarNav" aria-label="Navegación principal">
        <ul class="nav nav-pills flex-column">
            <?php foreach ($nav as $seccion => $items): ?>
                <li class="nav-section"><?= h($seccion) ?></li>
                <?php foreach ($items as $item): ?>
                    <li>
                        <a href="<?= base_url($item['ruta']) ?>"
                           class="nav-link<?= $active === $item['nav'] ? ' active' : '' ?>"
                           title="<?= h($item['label']) ?>">
                            <i class="bi <?= $item['icono'] ?>"></i>
                            <span class="nav-label"><?= h($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if (Auth::isAdmin()): ?>
                <li class="nav-section">Administración</li>
                <?php foreach ($navAdmin as $item): ?>
                    <li>
                        <a href="<?= base_url($item['ruta']) ?>"
                           class="nav-link<?= $active === $item['nav'] ? ' active' : '' ?>"
                           title="<?= h($item['label']) ?>">
                            <i class="bi <?= $item['icono'] ?>"></i>
                            <span class="nav-label"><?= h($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<div class="app-frame">
    <header class="app-topbar">
        <button type="button" class="btn-toggle" id="btnSidebarCollapse" aria-label="Abrir o cerrar menú">
            <i class="bi bi-list d-inline-flex d-lg-none"></i>
            <i class="bi bi-layout-sidebar d-none d-lg-inline-flex"></i>
        </button>

        <a href="<?= base_url('/') ?>" class="topbar-brand" title="Sistema de Marcaciones">
            <span class="topbar-brand-icon"><i class="bi bi-clock-history"></i></span>
            <span class="d-none d-sm-inline">Marcaciones</span>
        </a>

        <nav class="topbar-breadcrumb" aria-label="Miga de pan">
            <a href="<?= base_url('/') ?>" class="crumb-link">Inicio</a>
            <span class="crumb-sep bi bi-chevron-right"></span>
            <span class="crumb-current" title="<?= h($pageTitle) ?>"><?= h($pageTitle) ?></span>
        </nav>

        <span class="topbar-spacer"></span>

        <div class="topbar-right">
            <!-- Selector de tema -->
            <div class="dropdown">
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

            <!-- Menú de usuario -->
            <div class="dropdown">
                <button type="button" class="topbar-user-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de usuario">
                    <span class="user-avatar"><?= h($inicial) ?></span>
                    <span class="user-meta">
                        <span class="u-name"><?= h($nombre) ?></span>
                        <span class="u-role"><?= h($rolLabel) ?></span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><span class="dropdown-item-text fw-semibold"><?= h($nombre) ?></span></li>
                    <li><span class="dropdown-item-text small text-muted"><?= h($rolLabel) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= base_url('/logout') ?>">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="chrome-main <?= h($contentClass) ?>">
        <?= $content ?>
        <?php if ($contentClass !== 'chrome-main--fill'): ?>
            <footer class="app-footer">&copy; <?= date('Y') ?> Sistema de Marcaciones</footer>
        <?php endif; ?>
    </main>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
</div>
