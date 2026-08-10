<?php

declare(strict_types=1);

use App\Core\View;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Marcaciones — Calendario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= base_url('/assets/css/calendario.css') ?>">
</head>
<body>
<?= View::partial('layouts/partials/navbar', ['activeNav' => 'calendario']) ?>

<div class="app" id="app">
  <div class="hdr">
    <div class="mnav">
      <button class="ib" id="btn-prev">&#8249;</button>
      <h1 id="month-title"></h1>
      <button class="ib" id="btn-next">&#8250;</button>
    </div>
    <button class="pill" id="btn-today">Hoy</button>

    <div class="week-badge" id="week-badge">
      <span class="wbi" id="wbadge-num"></span>
      <span class="wbd" id="wbadge-range"></span>
    </div>
    <div class="mes-badge" id="mes-badge">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span id="mes-badge-text"></span>
    </div>

    <div class="seg">
      <button id="btn-dia"    data-modo="dia">Día</button>
      <button id="btn-semana" data-modo="semana">Semana</button>
      <button id="btn-mes"    data-modo="mes">Mes</button>
    </div>
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
        <div class="li"><span class="lb" style="background:rgba(37,99,235,.25);border:1px solid rgba(37,99,235,.4)"></span>Seleccion.</div>
      </div>
    </div>

    <div class="right">
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
        <button id="btn-exp-sem" style="padding:6px 12px;background:var(--grn);color:#fff;border:none;border-radius:var(--r2);font-weight:600;font-size:13px;transition:.15s;display:flex;align-items:center;gap:6px;height:36px;cursor:pointer;">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Inasistencias Semanal
        </button>
        <button id="btn-exp-mes" style="padding:6px 12px;background:var(--blue);color:#fff;border:none;border-radius:var(--r2);font-weight:600;font-size:13px;transition:.15s;display:flex;align-items:center;gap:6px;height:36px;cursor:pointer;">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Inasistencias Mensual
        </button>
        <button id="btn-exp-horas" style="padding:6px 12px;background:#7c3aed;color:#fff;border:none;border-radius:var(--r2);font-weight:600;font-size:13px;transition:.15s;display:flex;align-items:center;gap:6px;height:36px;cursor:pointer;">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Horas del Mes
        </button>
      </div>

      <div class="dcard" id="dcard">
        <div class="empty"><span class="sp"></span></div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="export-modal">
  <div class="modal-box">
    <div class="modal-icon" id="modal-icon">
      <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
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
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Descargar
      </button>
      <button class="modal-btn-cancel" id="modal-cancel">Cancelar</button>
    </div>
  </div>
</div>

<script>
window.D0 = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
window.CAL_CONFIG = {
    baseApp: <?= json_encode(base_url()) ?>,
    base: <?= json_encode(base_url('/calendario')) ?>,
    editarBase: <?= json_encode(base_url('/marcacion/editar') . '?') ?>
};
</script>
<script src="<?= base_url('/assets/js/calendario.js') ?>" defer></script>
</body>
</html>
