<?php

declare(strict_types=1);

use App\Core\Auth;
?>
<header class="app-header">
    <div class="container app-header-inner">
        <a class="app-brand" href="<?= base_url('/') ?>">Sistema de Marcaciones</a>

        <?php if (Auth::check()): ?>
            <nav class="app-nav">
                <a href="<?= base_url('/') ?>" class="<?= ($activeNav ?? '') === 'inicio' ? 'active' : '' ?>">Inicio</a>
                <a href="<?= base_url('/calendario') ?>" class="<?= ($activeNav ?? '') === 'calendario' ? 'active' : '' ?>">Calendario</a>
                <a href="<?= base_url('/importar_marcaciones.php') ?>" class="<?= ($activeNav ?? '') === 'importar' ? 'active' : '' ?>">Importar</a>
                <a href="<?= base_url('/observaciones') ?>" class="<?= ($activeNav ?? '') === 'observaciones' ? 'active' : '' ?>">Observaciones</a>
                <a href="<?= base_url('/consulta') ?>" class="<?= ($activeNav ?? '') === 'consulta' ? 'active' : '' ?>">Consulta</a>
                <?php if (Auth::isAdmin()): ?>
                    <a href="<?= base_url('/eliminar-mes') ?>" class="<?= ($activeNav ?? '') === 'eliminar-mes' ? 'active' : '' ?>">Eliminar mes</a>
                <?php endif; ?>
            </nav>

            <div class="app-user">
                <span class="app-user-name">Hola, <?= h(Auth::nombre() ?? '') ?></span>
                <span class="app-user-rol">(<?= h(Auth::rol() ?? '') ?>)</span>
                <a class="btn btn-ghost" href="<?= base_url('/logout') ?>">Salir</a>
            </div>
        <?php endif; ?>
    </div>
</header>
