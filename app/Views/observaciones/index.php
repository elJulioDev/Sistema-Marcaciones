<?php

declare(strict_types=1);

$urlPagina = static function (int $numPagina) {
    $query = $_GET;
    $query['p'] = $numPagina;

    return '?' . http_build_query($query);
};

$urlEstado = static function (string $estado) use ($periodo) {
    $query = ['estado' => $estado];
    if ($periodo !== '') {
        $query['periodo'] = $periodo;
    }

    return '?' . http_build_query($query);
};
?>
<div class="page-header">
    <div class="page-header-title">
        <span class="page-header-icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <h1>Observaciones de marcaciones</h1>
            <p class="page-header-sub">Incidencias registradas: observados, incompletos y errores.</p>
        </div>
    </div>
</div>

<section class="card">
    <div class="obs-filters">
        <div class="obs-tabs">
            <a href="<?= base_url('/observaciones') ?>" class="obs-tab <?= $filtroEstado === '' ? 'active' : '' ?>">Todos</a>
            <a href="<?= base_url('/observaciones') . $urlEstado('OBSERVADO') ?>" class="obs-tab <?= $filtroEstado === 'OBSERVADO' ? 'active' : '' ?>">Observados</a>
            <a href="<?= base_url('/observaciones') . $urlEstado('INCOMPLETO') ?>" class="obs-tab <?= $filtroEstado === 'INCOMPLETO' ? 'active' : '' ?>">Incompletos</a>
            <a href="<?= base_url('/observaciones') . $urlEstado('ERROR') ?>" class="obs-tab <?= $filtroEstado === 'ERROR' ? 'active' : '' ?>">Errores</a>
            <a href="<?= base_url('/observaciones') . $urlEstado('OK') ?>" class="obs-tab <?= $filtroEstado === 'OK' ? 'active' : '' ?>">OK</a>
        </div>

        <form method="get" class="obs-search">
            <?php if ($filtroEstado !== ''): ?>
                <input type="hidden" name="estado" value="<?= h($filtroEstado) ?>">
            <?php endif; ?>

            <div class="obs-search-fields">
                <div class="obs-field">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" placeholder="Buscar por nombre, número, RUT, dpto u observación" value="<?= h($q) ?>">
                </div>
                <div class="obs-field obs-field--sm">
                    <i class="bi bi-calendar3"></i>
                    <input type="month" name="periodo" value="<?= h($periodo) ?>">
                </div>
                <button type="submit" class="obs-btn obs-btn--primary">Buscar</button>
                <a class="obs-btn obs-btn--ghost" href="<?= base_url('/observaciones') . ($filtroEstado !== '' ? '?estado=' . urlencode($filtroEstado) : '') ?>">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="obs-table-wrap">
        <table class="obs-table">
            <thead>
                <tr>
                    <th class="col-fecha">Fecha</th>
                    <th class="col-func">Funcionario</th>
                    <th class="col-horario">Horario</th>
                    <th class="col-marc">Marcaciones</th>
                    <th class="col-estado">Estado</th>
                    <th class="col-obs">Observación</th>
                    <th class="col-accion">Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="7" class="empty">No se encontraron registros.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="col-fecha">
                            <span class="obs-dia"><?= h(nombre_dia_es($r['fecha'])) ?></span>
                            <span class="obs-fecha"><?= h(date('d/m/Y', strtotime($r['fecha']))) ?></span>
                        </td>
                        <td class="col-func">
                            <span class="obs-nombre"><?= h($r['nombre']) ?></span>
                            <span class="obs-meta">No. <?= h($r['numero']) ?> · <?= h($r['dpto']) ?></span>
                            <span class="obs-meta">RUT <?= h($r['rut_base']) ?></span>
                        </td>
                        <td class="col-horario">
                            <div class="obs-horario">
                                <span class="obs-ent"><?= $r['entrada'] ? h(substr((string) $r['entrada'], 0, 5)) : '—' ?></span>
                                <i class="bi bi-arrow-right-short"></i>
                                <span class="obs-sal"><?= $r['salida'] ? h(substr((string) $r['salida'], 0, 5)) : '—' ?></span>
                            </div>
                            <span class="obs-total"><?= $r['total_horas'] ? h(substr((string) $r['total_horas'], 0, 5)) : '—' ?></span>
                        </td>
                        <td class="col-marc">
                            <div class="obs-marcas">
                                <?php if (!empty($r['detalle_marcaciones'])): ?>
                                    <?php foreach ($r['detalle_marcaciones'] as $m): ?>
                                        <span class="obs-chip"><?= h(substr((string) $m['hora'], 0, 5)) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="obs-meta">Sin detalle</span>
                                <?php endif; ?>
                            </div>
                            <span class="obs-cant"><?= (int) $r['cantidad_marcaciones'] ?> marca<?= (int) $r['cantidad_marcaciones'] !== 1 ? 's' : '' ?></span>
                        </td>
                        <td class="col-estado">
                            <?php if ($r['estado'] === 'OK'): ?>
                                <span class="badge badge-ok">OK</span>
                            <?php elseif ($r['estado'] === 'OBSERVADO'): ?>
                                <span class="badge badge-obs">OBSERVADO</span>
                            <?php elseif ($r['estado'] === 'INCOMPLETO'): ?>
                                <span class="badge badge-inc">INCOMPLETO</span>
                            <?php else: ?>
                                <span class="badge badge-err">ERROR</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-obs">
                            <span class="obs-text"><?= nl2br(h($r['observacion'])) ?></span>
                            <?php if ((int) $r['editado_manual'] === 1): ?>
                                <span class="obs-editada">Editado <?= h(date('d/m H:i', strtotime($r['updated_at']))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="col-accion">
                            <a class="obs-btn-edit" href="<?= base_url('/marcacion/editar?id=' . (int) $r['id']) ?>"><i class="bi bi-pencil-square"></i> Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalRegistros > 0): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Mostrando registros <strong><?= $offset + 1 ?></strong> al <strong><?= min($offset + $registrosPorPagina, $totalRegistros) ?></strong> de un total de <strong><?= $totalRegistros ?></strong>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <div class="pagination">
                    <?php if ($paginaActual > 1): ?>
                        <a href="<?= $urlPagina(1) ?>" title="Primera página">&laquo; Primera</a>
                        <a href="<?= $urlPagina($paginaActual - 1) ?>" title="Página anterior">&lsaquo; Anterior</a>
                    <?php endif; ?>

                    <?php
                    $inicioVentana = max(1, $paginaActual - 2);
                    $finVentana    = min($totalPaginas, $paginaActual + 2);
                    for ($i = $inicioVentana; $i <= $finVentana; $i++): ?>
                        <a href="<?= $urlPagina($i) ?>" class="<?= $i === $paginaActual ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($paginaActual < $totalPaginas): ?>
                        <a href="<?= $urlPagina($paginaActual + 1) ?>" title="Página siguiente">Siguiente &rsaquo;</a>
                        <a href="<?= $urlPagina($totalPaginas) ?>" title="Última página">Última &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>