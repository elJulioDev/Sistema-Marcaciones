<?php

declare(strict_types=1);

use App\Core\View;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Importar Marcaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= base_url('/assets/img/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('/assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= base_url('/assets/css/importar.css') ?>">
</head>
<body class="imp-body">
<?= View::partial('layouts/partials/navbar', ['activeNav' => 'importar']) ?>

<div class="main-scroll">
<div class="wrap">

<div class="card" id="upload-card">
  <div class="card-hd">
    <svg width="26" height="26" fill="none" stroke="var(--blue)" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="17 8 12 3 7 8"/>
      <line x1="12" y1="3" x2="12" y2="15"/>
    </svg>
    <h1>Importar Marcaciones</h1>
  </div>
  <p class="card-sub">
    Sube el archivo <strong>TXT o CSV</strong> exportado desde el reloj de control.
    Los registros duplicados se detectan y omiten automáticamente usando un hash
    por registro. La inserción se realiza en lotes para mayor rendimiento.
  </p>

  <div class="alert-err" id="alert-err">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
      <line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span id="alert-err-msg"></span>
  </div>

  <div class="dropzone" id="dropzone">
    <input type="file" id="file-input" accept=".txt,.csv">
    <div class="dz-icon" id="dz-icon">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
           id="dz-svg">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
    </div>
    <div class="dz-title" id="dz-title">Arrastra tu archivo aquí</div>
    <div class="dz-sub"   id="dz-sub">o haz clic para seleccionar &nbsp;·&nbsp; .TXT o .CSV</div>
    <div class="dz-chips" id="dz-chips" style="display:none;">
      <span class="dz-chip" id="dz-chip-name"></span>
      <span class="dz-chip" id="dz-chip-size"></span>
    </div>
  </div>

  <div class="form-grid">
    <div class="fg">
      <label for="periodo">Período</label>
      <input type="month" id="periodo" placeholder="YYYY-MM">
    </div>
    <div class="fg">
      <label for="observacion">Observación de la carga</label>
      <textarea id="observacion" placeholder="Ej: Marcaciones marzo 2025 · Carga corregida..."></textarea>
    </div>
  </div>

  <button class="btn-import" id="btn-import" disabled>
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
    </svg>
    <span id="btn-import-text">Selecciona un archivo para continuar</span>
  </button>
</div>

<div class="card prog-card" id="prog-card">

  <div class="prog-header">
    <div class="prog-spinner" id="prog-spinner"></div>
    <div>
      <div class="prog-title" id="prog-title">Procesando importación...</div>
      <div class="prog-sub"   id="prog-sub">Esto puede tardar algunos segundos.</div>
    </div>
  </div>

  <div class="pbar-wrap">
    <div class="pbar-meta">
      <span class="pbar-msg" id="pbar-msg">Iniciando...</span>
      <span class="pbar-pct" id="pbar-pct">0 %</span>
    </div>
    <div class="pbar-track">
      <div class="pbar-fill" id="pbar-fill"></div>
    </div>
  </div>

  <div class="steps">
    <div class="step" id="step-1">
      <div class="step-num" data-n="1">1</div>
      <div class="step-text">Leyendo y validando el archivo</div>
      <span class="step-badge" id="badge-1"></span>
    </div>
    <div class="step" id="step-2">
      <div class="step-num" data-n="2">2</div>
      <div class="step-text">Insertando registros en la base de datos</div>
      <span class="step-badge" id="badge-2"></span>
    </div>
    <div class="step" id="step-3">
      <div class="step-num" data-n="3">3</div>
      <div class="step-text">Calculando resúmenes de asistencia</div>
      <span class="step-badge" id="badge-3"></span>
    </div>
  </div>

</div>

<div class="card res-card" id="res-card">
  <div class="res-hd">
    <svg width="22" height="22" fill="none" stroke="var(--grn)" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
      <polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <h2>¡Importación completada!</h2>
  </div>
  <p class="res-sub">Los datos fueron procesados e integrados al sistema correctamente.</p>

  <div class="res-grid" id="res-grid"></div>

  <div class="btn-group">
    <button class="btn-sec primary" id="btn-again">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <polyline points="23 4 23 10 17 10"/>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
      </svg>
      Nueva importación
    </button>
    <a class="btn-sec" href="<?= base_url('/calendario') ?>">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <rect x="3" y="4" width="18" height="18" rx="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      Ver calendario
    </a>
    <a class="btn-sec" href="<?= base_url('/observaciones') ?>">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      Ver observaciones
    </a>
  </div>
</div>

</div></div>

<script>
window.IMPORT_CONFIG = {
    importUrl: <?= json_encode(base_url('/importar/importar')) ?>,
    calendarioUrl: <?= json_encode(base_url('/calendario')) ?>,
    observacionesUrl: <?= json_encode(base_url('/observaciones')) ?>
};
</script>
<script src="<?= base_url('/assets/js/importar.js') ?>" defer></script>
</body>
</html>
