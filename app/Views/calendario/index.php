<?php

declare(strict_types=1);

use App\Core\View;

ob_start(); ?>
<div class="app" id="app">
  <div class="hdr">
    <div class="mnav">
      <button class="ib" id="btn-prev" aria-label="Mes anterior">&#8249;</button>
      <h1 id="month-title"></h1>
      <button class="ib" id="btn-next" aria-label="Mes siguiente">&#8250;</button>
    </div>
    <button class="pill" id="btn-today">Hoy</button>

    <div class="seg">
      <button id="btn-dia" data-modo="dia">Día</button>
      <button id="btn-semana" data-modo="semana">Semana</button>
      <button id="btn-mes" data-modo="mes">Mes</button>
    </div>

    <div class="exp-dd" id="export-dropdown">
      <button class="ib exp-toggle" id="btn-export-toggle" aria-label="Exportar">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
      </button>
      <div class="exp-menu" id="export-menu">
        <button id="btn-exp-sem">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Inasistencias Semanal
        </button>
        <button id="btn-exp-mes">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Inasistencias Mensual
        </button>
        <button id="btn-exp-horas">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Horas del Mes
        </button>
      </div>
    </div>
  </div>

  <div class="filters-bar">
    <input type="text" id="f-q" placeholder="Buscar por nombre, número o RUT..." autocomplete="off">
    <select id="f-dpto"><option value="">Todos los departamentos</option></select>
    <select id="f-estado">
      <option value="">Todos los estados</option>
      <option value="OK">OK</option>
      <option value="OBSERVADO">Observado</option>
      <option value="INCOMPLETO">Incompleto</option>
      <option value="ERROR">Error</option>
    </select>
    <label class="tgl-btn" id="lbl-ausencias">
      <input type="checkbox" id="f-ausencias" style="display:none;">
      <div class="tgl-box"></div>
      <span>Solo inasistencias</span>
    </label>
  </div>

  <div class="grid">
    <div class="cc">
      <div class="cg" id="cal"></div>
      <div class="sr" id="stats"></div>
      <div class="lgnd">
        <div class="li"><span class="lb" style="background:var(--grn)"></span>OK</div>
        <div class="li"><span class="lb" style="background:var(--amb)"></span>Incidencias</div>
        <div class="li"><span class="lb" style="background:var(--sky)"></span>Observado</div>
        <div class="li"><span class="lb" style="background:var(--red)"></span>Error</div>
      </div>
    </div>

    <div class="right">
      <div class="dcard" id="dcard">
        <div class="empty"><span class="sp"></span></div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="export-modal">
  <div class="modal-box">
    <div class="modal-icon" id="modal-icon">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
    </div>
    <h3 id="modal-title"></h3>
    <div class="modal-range">
      <div>
        <div class="mr-label" id="modal-range-label"></div>
        <div class="mr-val" id="modal-range-val"></div>
      </div>
    </div>
    <p class="modal-note" id="modal-note"></p>
    <div class="modal-actions">
      <button class="modal-btn-confirm" id="modal-confirm">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Descargar
      </button>
      <button class="modal-btn-cancel" id="modal-cancel">Cancelar</button>
    </div>
  </div>
</div>

<script>
window.D0 = <?= json_encode($data['data'], JSON_UNESCAPED_UNICODE) ?>;
window.CAL_CONFIG = {
    baseApp: <?= json_encode(base_url()) ?>,
    base: <?= json_encode(base_url('/calendario')) ?>,
    editarBase: <?= json_encode(base_url('/marcacion/editar') . '?') ?>
};
</script>
<?php $contenidoCalendario = ob_get_clean(); ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Marcaciones — Calendario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= base_url('/assets/img/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
<script>
try {
    var t = localStorage.getItem('sm-theme')
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
} catch (e) { document.documentElement.setAttribute('data-theme', 'light'); }
</script>
</head>
<body class="cal-body page-fill">
<?= View::partial('layouts/partials/chrome', [
    'content'      => $contenidoCalendario,
    'activeNav'    => 'calendario',
    'pageTitle'    => 'Calendario de marcaciones',
    'contentClass' => 'chrome-main--fill',
]) ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('/assets/js/app.js') ?>" defer></script>
<script src="<?= base_url('/assets/js/calendario.js') ?>" defer></script>
</body>
</html>
