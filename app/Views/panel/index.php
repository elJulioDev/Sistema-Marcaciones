<?php

declare(strict_types=1);

$fechaActual = new DateTimeImmutable();
$nombreDia   = nombre_dia_es($fechaActual->format('Y-m-d'));
?>
<div class="page-header">
    <div class="page-header-title">
        <span class="page-header-icon"><i class="bi bi-speedometer2"></i></span>
        <div>
            <h1>Bienvenido, <?= h(\App\Core\Auth::nombre()) ?></h1>
            <p class="page-header-sub">Gestiona la asistencia del personal: revisa el calendario de marcaciones, importa archivos de los relojes y corrige incidencias.</p>
        </div>
    </div>
    <span class="page-header-date"><i class="bi bi-calendar2-week"></i><?= $nombreDia ?> <?= $fechaActual->format('d/m/Y') ?></span>
</div>

<div class="section-title">Acceso rápido</div>
<div class="panel-grid">
    <a href="<?= base_url('/calendario') ?>" class="panel-card panel-calendario">
        <span class="panel-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </span>
        <span class="panel-info">
            <span class="panel-title">Calendario Visual</span>
            <span class="panel-desc">Estado de marcaciones diarias, semanales y mensuales.</span>
        </span>
        <span class="panel-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </span>
    </a>

    <a href="<?= base_url('/importar') ?>" class="panel-card panel-importar">
        <span class="panel-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </span>
        <span class="panel-info">
            <span class="panel-title">Importar Archivos</span>
            <span class="panel-desc">Sube archivos TXT/CSV desde los relojes de control.</span>
        </span>
        <span class="panel-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </span>
    </a>

    <a href="<?= base_url('/observaciones') ?>" class="panel-card panel-observaciones">
        <span class="panel-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </span>
        <span class="panel-info">
            <span class="panel-title">Observaciones</span>
            <span class="panel-desc">Corrige incidencias y errores en la asistencia.</span>
        </span>
        <span class="panel-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </span>
    </a>

    <a href="<?= base_url('/consulta') ?>" class="panel-card panel-consulta">
        <span class="panel-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
        </span>
        <span class="panel-info">
            <span class="panel-title">Buscar Funcionario</span>
            <span class="panel-desc">Busca marcaciones e historial usando el RUT.</span>
        </span>
        <span class="panel-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </span>
    </a>
</div>
