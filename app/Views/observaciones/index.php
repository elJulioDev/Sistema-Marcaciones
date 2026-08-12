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
    <div class="topbar">
        <a href="<?= base_url('/observaciones') ?>" class="<?= $filtroEstado === '' ? 'active' : '' ?>">Todos</a>
        <a href="<?= base_url('/observaciones') . $urlEstado('OBSERVADO') ?>" class="<?= $filtroEstado === 'OBSERVADO' ? 'active' : '' ?>">Observados</a>
        <a href="<?= base_url('/observaciones') . $urlEstado('INCOMPLETO') ?>" class="<?= $filtroEstado === 'INCOMPLETO' ? 'active' : '' ?>">Incompletos</a>
        <a href="<?= base_url('/observaciones') . $urlEstado('ERROR') ?>" class="<?= $filtroEstado === 'ERROR' ? 'active' : '' ?>">Errores</a>
        <a href="<?= base_url('/observaciones') . $urlEstado('OK') ?>" class="<?= $filtroEstado === 'OK' ? 'active' : '' ?>">OK</a>
    </div>

    <form method="get" class="filters">
        <?php if ($filtroEstado !== ''): ?>
            <input type="hidden" name="estado" value="<?= h($filtroEstado) ?>">
        <?php endif; ?>

        <input
            type="text"
            name="q"
            placeholder="Buscar por nombre, número, rut base, dpto u observación"
            value="<?= h($q) ?>"
        >
        <input
            type="month"
            name="periodo"
            value="<?= h($periodo) ?>"
        >
        <button type="submit">Buscar</button>

        <a class="secondary" href="<?= base_url('/observaciones') . ($filtroEstado !== '' ? '?estado=' . urlencode($filtroEstado) : '') ?>">Limpiar</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Funcionario</th>
                    <th>Dpto.</th>
                    <th>No.</th>
                    <th>Cant. marcaciones</th>
                    <th>Detalle marcaciones</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Observación</th>
                    <th>Editado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="13" class="empty">No se encontraron registros.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <strong><?= h(nombre_dia_es($r['fecha'])) ?></strong><br>
                            <?= h(date('d/m/Y', strtotime($r['fecha']))) ?>
                        </td>
                        <td>
                            <strong><?= h($r['nombre']) ?></strong><br>
                            <span class="small">RUT base: <?= h($r['rut_base']) ?></span>
                        </td>
                        <td><?= h($r['dpto']) ?></td>
                        <td><?= h($r['numero']) ?></td>
                        <td style="text-align:center;"><?= (int) $r['cantidad_marcaciones'] ?></td>
                        <td>
                            <div class="marcas">
                                <?php if (!empty($r['detalle_marcaciones'])): ?>
                                    <?php foreach ($r['detalle_marcaciones'] as $m): ?>
                                        <span class="marca-item"><?= h(substr((string) $m['hora'], 0, 5)) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="small">Sin detalle</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong><?= $r['entrada'] ? h(substr((string) $r['entrada'], 0, 5)) : '-' ?></strong></td>
                        <td><strong><?= $r['salida'] ? h(substr((string) $r['salida'], 0, 5)) : '-' ?></strong></td>
                        <td><?= $r['total_horas'] ? h(substr((string) $r['total_horas'], 0, 5)) : '-' ?></td>
                        <td>
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
                        <td><?= nl2br(h($r['observacion'])) ?></td>
                        <td>
                            <?php if ((int) $r['editado_manual'] === 1): ?>
                                <span class="small">Sí<br><?= h(date('d/m H:i', strtotime($r['updated_at']))) ?></span>
                            <?php else: ?>
                                <span class="small">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn-editar" href="<?= base_url('/marcacion/editar?id=' . (int) $r['id']) ?>">Editar</a>
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
