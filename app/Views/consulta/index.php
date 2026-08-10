<?php

declare(strict_types=1);
?>
<section class="card">
    <h1>Consulta de marcaciones</h1>

    <form method="get" class="filters">
        <input
            type="text"
            name="rut"
            placeholder="Ingresa RUT, ej: 17.520.205-0"
            value="<?= h($rut) ?>"
        >
        <input
            type="month"
            name="periodo"
            value="<?= h($periodo) ?>"
        >
        <button type="submit">Consultar</button>
        <a class="secondary" href="<?= base_url('/consulta') ?>">Limpiar</a>

        <?php if ($funcionario): ?>
            <button type="button" class="btn-print" onclick="window.print()">
                Imprimir / Guardar PDF
            </button>
        <?php endif; ?>
    </form>

    <?php if ($error !== ''): ?>
        <div class="alert err"><?= h($error) ?></div>
    <?php endif; ?>
</section>

<?php if ($funcionario): ?>
    <section class="card">
        <div class="info-grid">
            <div class="info-box">
                <strong>Funcionario</strong>
                <div><?= h($funcionario['nombre']) ?></div>
            </div>
            <div class="info-box">
                <strong>Departamento</strong>
                <div><?= h($funcionario['dpto']) ?></div>
            </div>
            <div class="info-box">
                <strong>No.</strong>
                <div><?= h($funcionario['numero']) ?></div>
            </div>
            <div class="info-box">
                <strong>RUT consultado</strong>
                <div><?= h(formatear_rut($rut)) ?></div>
            </div>
            <div class="info-box">
                <strong>Total días encontrados</strong>
                <div><?= count($resumenes) ?></div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cant. marcaciones</th>
                        <th>Detalle marcaciones</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Observación</th>
                        <th>Editado</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$resumenes): ?>
                    <tr>
                        <td colspan="9" class="empty">No existen registros para el período consultado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($resumenes as $r): ?>
                        <tr>
                            <td>
                                <strong><?= h(nombre_dia_es($r['fecha'])) ?></strong><br>
                                <?= h(date('d/m/Y', strtotime($r['fecha']))) ?>
                            </td>
                            <td><?= (int) $r['cantidad_marcaciones'] ?></td>
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
                            <td><?= $r['entrada'] ? h(substr((string) $r['entrada'], 0, 5)) : '-' ?></td>
                            <td><?= $r['salida'] ? h(substr((string) $r['salida'], 0, 5)) : '-' ?></td>
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
                                    <span class="small">Sí<br><?= h($r['updated_at']) ?></span>
                                <?php else: ?>
                                    <span class="small">No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
