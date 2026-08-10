<?php

declare(strict_types=1);

use App\Core\Auth;

$active = $activeNav ?? '';
?>
<header class="app-header">
    <div class="container app-header-inner">
        <a class="app-brand" href="<?= base_url('/') ?>">
            <span class="app-brand-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </span>
            <span class="app-brand-text">Sistema de Marcaciones</span>
        </a>

        <button class="app-hamburger" id="app-hamburger" aria-label="Abrir menú" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <?php if (Auth::check()): ?>
            <nav class="app-nav" id="app-nav">
                <a href="<?= base_url('/') ?>" class="<?= $active === 'inicio' ? 'active' : '' ?>">Inicio</a>
                <a href="<?= base_url('/calendario') ?>" class="<?= $active === 'calendario' ? 'active' : '' ?>">Calendario</a>
                <a href="<?= base_url('/importar') ?>" class="<?= $active === 'importar' ? 'active' : '' ?>">Importar</a>
                <a href="<?= base_url('/observaciones') ?>" class="<?= $active === 'observaciones' ? 'active' : '' ?>">Observaciones</a>
                <a href="<?= base_url('/consulta') ?>" class="<?= $active === 'consulta' ? 'active' : '' ?>">Consulta</a>
                <?php if (Auth::isAdmin()): ?>
                    <a href="<?= base_url('/eliminar-mes') ?>" class="<?= $active === 'eliminar-mes' ? 'active' : '' ?>">Eliminar mes</a>
                <?php endif; ?>

                <div class="app-nav-user">
                    <div class="app-user-meta">
                        <span class="app-user-name"><?= h(Auth::nombre() ?? '') ?></span>
                        <span class="app-user-rol"><?= h(Auth::rol() ?? '') ?></span>
                    </div>
                    <a class="app-logout" href="<?= base_url('/logout') ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Salir
                    </a>
                </div>
            </nav>

            <div class="app-user">
                <div class="app-user-meta">
                    <span class="app-user-name">Hola, <?= h(Auth::nombre() ?? '') ?></span>
                    <span class="app-user-rol"><?= h(Auth::rol() ?? '') ?></span>
                </div>
                <a class="app-logout" href="<?= base_url('/logout') ?>">Salir</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<div class="app-nav-overlay" id="app-nav-overlay"></div>

<script>
(function () {
    var nav = document.getElementById('app-nav');
    var burger = document.getElementById('app-hamburger');
    var overlay = document.getElementById('app-nav-overlay');
    if (!nav || !burger) return;

    function setOpen(open) {
        nav.classList.toggle('open', open);
        burger.classList.toggle('open', open);
        overlay && overlay.classList.toggle('open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';
    }

    burger.addEventListener('click', function () {
        setOpen(!nav.classList.contains('open'));
    });
    overlay && overlay.addEventListener('click', function () { setOpen(false); });
    nav.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { setOpen(false); });
    });
})();
</script>
