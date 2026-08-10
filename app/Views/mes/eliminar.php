<?php

declare(strict_types=1);
?>
<section class="card">
    <h1 class="danger-title">Eliminar marcaciones de un mes</h1>
    <p>Esta herramienta borrará de forma permanente <strong>todos los registros, resúmenes calculados y el historial de importación</strong> del mes seleccionado. Úsala para limpiar datos basura generados por pruebas o importaciones erróneas.</p>

    <?php if ($mensaje !== ''): ?>
        <div class="alert ok"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="mes">Selecciona el mes a eliminar:</label>
            <input type="month" id="mes" name="mes" required>
        </div>

        <div class="form-group">
            <div class="checkbox-group">
                <input type="checkbox" id="confirmacion" name="confirmacion" required>
                <label for="confirmacion">
                    <strong>Estoy seguro.</strong> Entiendo que esta acción no se puede deshacer y eliminará tanto los datos del reloj como el registro de la importación asociada a ese período.
                </label>
            </div>
        </div>

        <button type="submit" class="btn-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
                <polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            Eliminar datos del mes
        </button>
    </form>
</section>

<section class="card">
    <h2>Historial de Archivos Importados</h2>
    <p>Lista de archivos procesados y guardados en la tabla de registros. Al eliminar un mes en el formulario superior, se eliminará también su registro de esta lista.</p>

    <?php if ($errorLista !== ''): ?>
        <div class="alert err">Error al cargar la tabla de importaciones: <?= h($errorLista) ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <?php if (count($importaciones) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Nombre del Archivo</th>
                            <th style="width: 120px;">Período</th>
                            <th>Líneas leídas</th>
                            <th>Insertadas</th>
                            <th>Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importaciones as $imp): ?>
                        <tr>
                            <td><?= h($imp['id']) ?></td>
                            <td><strong><?= h($imp['nombre_archivo']) ?></strong></td>
                            <td>
                                <?php if (!empty($imp['periodo'])): ?>
                                    <span class="badge blue"><?= h($imp['periodo']) ?></span>
                                <?php else: ?>
                                    <span class="badge">Sin periodo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((int) $imp['total_lineas'], 0, ',', '.') ?></td>
                            <td><?= number_format((int) $imp['total_insertadas'], 0, ',', '.') ?></td>
                            <td style="color: #6b7280;"><?= h($imp['observacion']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-list">
                    No hay registros de importaciones guardados en el sistema actualmente.
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
