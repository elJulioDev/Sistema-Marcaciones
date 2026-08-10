<?php

declare(strict_types=1);

$d = $data['data'] ?? [];

/** @var array $d  (kpis, porDia, porDpto, incidencias, importaciones, presentes, ausentes, estados) */

$hoy  = $d['hoy'];
$kpis = $d['kpis'];

$totalEstados = $kpis['ok'] + $kpis['obs'] + $kpis['inc'] + $kpis['err'];
$presentismo  = $totalEstados > 0 ? round(($kpis['ok'] / $totalEstados) * 100, 1) : 0;
$incidencias  = $kpis['obs'] + $kpis['inc'] + $kpis['err'];

$colores = [
    'ok'  => 'var(--color-success)',
    'obs' => 'var(--color-info)',
    'inc' => 'var(--color-warning)',
    'err' => 'var(--color-danger)',
];

/* Gradiente del donut: acumulamos porcentajes sobre el total. */
$acum = 0;
$seg  = [];
foreach (['ok', 'obs', 'inc', 'err'] as $clave) {
    $n  = $kpis[$clave];
    $in = $acum;
    $acum += $totalEstados > 0 ? ($n / $totalEstados) * 100 : 0;
    if ($n > 0) {
        $seg[] = $colores[$clave] . ' ' . round($in, 2) . '% ' . round($acum, 2) . '%';
    }
}
$conic = 'conic-gradient(' . implode(', ', $seg) . ')';

function dash_horas(int $segundos): string
{
    $h = intdiv($segundos, 3600);
    $m = intdiv($segundos % 3600, 60);

    return ($h > 0 ? $h . ' h ' : '') . $m . ' min';
}

function dash_dia_corto(string $fecha): string
{
    $d = new DateTimeImmutable($fecha);

    return $d->format('D j');
}

$estadoLabel = [
    'OK'         => 'OK',
    'OBSERVADO'  => 'OBSERVADO',
    'INCOMPLETO' => 'INCOMPLETO',
    'ERROR'      => 'ERROR',
];
$estadoBadge = [
    'OK'         => 'badge-ok',
    'OBSERVADO'  => 'badge-obs',
    'INCOMPLETO' => 'badge-inc',
    'ERROR'      => 'badge-err',
];
?>
<div class="page-header">
    <div class="page-header-title">
        <span class="page-header-icon"><i class="bi bi-speedometer2"></i></span>
        <div>
            <h1>Panel de control</h1>
            <p class="page-header-sub">Resumen administrativo de la asistencia del personal.</p>
        </div>
    </div>
    <span class="page-header-date"><i class="bi bi-calendar2-week"></i><?= nombre_dia_es($hoy) ?> <?= (new DateTimeImmutable($hoy))->format('d/m/Y') ?></span>
</div>

<div class="dash-filters">
    <div class="month-nav">
        <a class="btn btn-ghost" href="<?= base_url('/dashboard?mes=' . $d['mesPrev']) ?>" title="Mes anterior"><i class="bi bi-chevron-left"></i></a>
        <span class="month-label"><?= h($d['mesLabel']) ?></span>
        <a class="btn btn-ghost" href="<?= base_url('/dashboard?mes=' . $d['mesNext']) ?>" title="Mes siguiente"><i class="bi bi-chevron-right"></i></a>
    </div>
    <?php if ($d['mes'] !== date('Y-m')): ?>
        <a class="btn btn-secondary" href="<?= base_url('/dashboard') ?>">Mes actual</a>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= base_url('/calendario?mes=' . $d['mes']) ?>"><i class="bi bi-calendar3"></i> Ver en calendario</a>
</div>

<!-- KPIs -->
<div class="kpis-grid">
    <div class="kpi-card">
        <span class="kpi-icon primary"><i class="bi bi-people"></i></span>
        <div>
            <div class="kpi-value"><?= number_format($kpis['empleados'], 0, ',', '.') ?></div>
            <div class="kpi-label">Empleados con marcas</div>
        </div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon info"><i class="bi bi-fingerprint"></i></span>
        <div>
            <div class="kpi-value"><?= number_format($kpis['marcas'], 0, ',', '.') ?></div>
            <div class="kpi-label">Marcas registradas</div>
        </div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon ok"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="kpi-value"><?= number_format($presentismo, 1, ',', '.') ?>%</div>
            <div class="kpi-label">Presentismo (días OK)</div>
        </div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon warning"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <div class="kpi-value"><?= number_format($incidencias, 0, ',', '.') ?></div>
            <div class="kpi-label">Incidencias del mes</div>
        </div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon primary"><i class="bi bi-clock-history"></i></span>
        <div>
            <div class="kpi-value"><?= dash_horas($kpis['horas_seg']) ?></div>
            <div class="kpi-label">Horas trabajadas</div>
        </div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon info"><i class="bi bi-calendar2-week"></i></span>
        <div>
            <div class="kpi-value"><?= $kpis['dias_datos'] ?></div>
            <div class="kpi-label">Días con datos</div>
        </div>
    </div>
</div>

<div class="dash-grid">
    <!-- Distribución por estado -->
    <section class="dash-card">
        <div class="dash-card-head">
            <h2 class="dash-card-title"><i class="bi bi-pie-chart"></i> Estado de los registros</h2>
            <a class="dash-card-link" href="<?= base_url('/calendario?mes=' . $d['mes'] . '&modo=mes') ?>">Ver detalle <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="dash-card-body">
            <div class="donut-wrap">
                <div class="donut" style="background: <?= $conic ?>">
                    <div class="donut-center">
                        <strong><?= number_format($totalEstados, 0, ',', '.') ?></strong>
                        <span>registros</span>
                    </div>
                </div>
                <div class="legend">
                    <?php foreach (['ok' => 'OK', 'obs' => 'Observado', 'inc' => 'Incompleto', 'err' => 'Error'] as $clave => $label): ?>
                        <?php $n = $kpis[$clave]; $pct = $totalEstados > 0 ? round(($n / $totalEstados) * 100, 1) : 0; ?>
                        <div class="legend-item">
                            <span class="legend-dot" style="background: <?= $colores[$clave] ?>"></span>
                            <span><?= $label ?></span>
                            <span class="legend-count"><?= number_format($n, 0, ',', '.') ?></span>
                            <span class="legend-pct"><?= number_format($pct, 1, ',', '.') ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Marcas por día -->
    <section class="dash-card">
        <div class="dash-card-head">
            <h2 class="dash-card-title"><i class="bi bi-bar-chart"></i> Marcas por día — <?= h($d['mesLabel']) ?></h2>
        </div>
        <div class="dash-card-body">
            <div class="bars">
                <?php foreach ($d['porDia'] as $bar): ?>
                    <div class="bar-col" title="<?= $bar['fecha'] ?> — <?= $bar['total'] ?> marcas">
                        <span class="bar-count"><?= $bar['total'] > 0 ? $bar['total'] : '' ?></span>
                        <span class="bar" style="height: <?= ($bar['total'] / $d['maxDia']) * 100 ?>%"></span>
                        <span class="bar-label"><?= (int) $bar['dia'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<div class="dash-grid">
    <!-- Presentes y ausentes hoy -->
    <section class="dash-card">
        <div class="dash-card-head">
            <h2 class="dash-card-title"><i class="bi bi-person-check"></i> Hoy en el sistema</h2>
            <span class="dash-card-link"><?= h(dash_dia_corto($hoy)) ?></span>
        </div>
        <div class="dash-card-body">
            <?php if (empty($d['presentes']) && empty($d['ausentes'])): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox empty-icon"></i>
                    <p>No hay registros para hoy.</p>
                </div>
            <?php else: ?>
                <div class="mini-list">
                    <?php foreach ($d['presentes'] as $p): ?>
                        <div class="mini-list-item">
                            <span class="who">
                                <strong><?= h($p['nombre']) ?></strong>
                                <small><?= h($p['dpto']) ?></small>
                            </span>
                            <span class="when"><i class="bi bi-box-arrow-in-right me-1"></i><?= h($p['entrada'] ?: '—') ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($d['ausentes'] as $a): ?>
                        <div class="mini-list-item">
                            <span class="who">
                                <strong><?= h($a['nombre']) ?></strong>
                                <small><?= h($a['dpto']) ?></small>
                            </span>
                            <span class="when text-danger"><i class="bi bi-person-x me-1"></i>Sin marca</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Por departamento -->
    <section class="dash-card">
        <div class="dash-card-head">
            <h2 class="dash-card-title"><i class="bi bi-diagram-3"></i> Distribución por departamento</h2>
            <a class="dash-card-link" href="<?= base_url('/consulta') ?>">Consultar <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="dash-card-body">
            <?php if (empty($d['porDpto'])): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox empty-icon"></i>
                    <p>Sin datos en el período.</p>
                </div>
            <?php else: ?>
                <?php $maxEmps = max(array_column($d['porDpto'], 'empleados') ?: [1]); ?>
                <?php foreach ($d['porDpto'] as $dep): ?>
                    <div class="dpto-row">
                        <div class="dpto-head">
                            <strong><?= h($dep['dpto'] ?: 'Sin departamento') ?></strong>
                            <small><?= $dep['empleados'] ?> emp. · <?= number_format($dep['registros'], 0, ',', '.') ?> reg. · <?= dash_horas((int) $dep['horas_seg']) ?></small>
                        </div>
                        <div class="dpto-track">
                            <div class="dpto-fill" style="width: <?= round(($dep['empleados'] / $maxEmps) * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="dash-grid">
    <!-- Últimas incidencias -->
    <section class="dash-card">
        <div class="dash-card-head">
            <h2 class="dash-card-title"><i class="bi bi-exclamation-triangle"></i> Últimas incidencias</h2>
            <a class="dash-card-link" href="<?= base_url('/observaciones') ?>">Ver todas <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="dash-card-body">
            <?php if (empty($d['incidencias'])): ?>
                <div class="empty-state">
                    <i class="bi bi-check2-all empty-icon"></i>
                    <p>Sin incidencias pendientes. </p>
                </div>
            <?php else: ?>
                <div class="table-wrap table-wrap--compact">
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Funcionario</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($d['incidencias'] as $inc): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($inc['nombre']) ?></strong>
                                        <br><small class="muted"><?= h($inc['dpto']) ?> · <?= h($inc['rut_base']) ?></small>
                                    </td>
                                    <td><?= h($inc['fecha']) ?></td>
                                    <td><span class="badge <?= $estadoBadge[$inc['estado']] ?? 'badge-err' ?>"><?= h($estadoLabel[$inc['estado']] ?? $inc['estado']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Últimas importaciones -->
    <section class="dash-card">
        <div class="dash-card-head">
            <h2 class="dash-card-title"><i class="bi bi-cloud-arrow-up"></i> Últimas importaciones</h2>
            <a class="dash-card-link" href="<?= base_url('/importar') ?>">Importar <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="dash-card-body">
            <?php if (empty($d['importaciones'])): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox empty-icon"></i>
                    <p>Aún no se han importado archivos.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap table-wrap--compact">
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Archivo</th>
                                <th>Período</th>
                                <th>Insertadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($d['importaciones'] as $imp): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($imp['nombre_archivo']) ?></strong>
                                        <br><small class="muted"><?= h($imp['created_at']) ?></small>
                                    </td>
                                    <td><?= h($imp['periodo'] ?: '—') ?></td>
                                    <td><?= number_format((int) $imp['total_insertadas'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
