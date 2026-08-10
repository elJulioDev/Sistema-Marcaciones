<?php

declare(strict_types=1);

$estadoClase = [
    'OK' => 'badge-ok',
    'OBSERVADO' => 'badge-obs',
    'INCOMPLETO' => 'badge-inc',
    'ERROR' => 'badge-err',
];
?>
<section class="card">
    <h1>Editar marcación resumen</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="alert ok"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert err"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="info-grid">
        <div class="info">
            <strong>Funcionario</strong>
            <span><?= h($registro['nombre']) ?></span>
        </div>
        <div class="info">
            <strong>Departamento</strong>
            <span><?= h($registro['dpto']) ?></span>
        </div>
        <div class="info">
            <strong>No.</strong>
            <span><?= h($registro['numero']) ?></span>
        </div>
        <div class="info">
            <strong>Fecha</strong>
            <span><?= h(date('d/m/Y', strtotime($registro['fecha']))) ?></span>
        </div>
        <div class="info">
            <strong>Marcaciones del día</strong>
            <span><?= (int) $registro['cantidad_marcaciones'] ?></span>
        </div>
        <div class="info">
            <strong>Estado actual</strong>
            <span>
                <span class="badge <?= $estadoClase[$registro['estado']] ?? 'badge-err' ?>"><?= h($registro['estado']) ?></span>
            </span>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="return_url" value="<?= h($returnUrl) ?>">
        <div class="form-grid">
            <div>
                <label for="entrada">Entrada</label>
                <input
                    type="time"
                    id="entrada"
                    name="entrada"
                    value="<?= $registro['entrada'] ? h(substr((string) $registro['entrada'], 0, 5)) : '' ?>"
                >
            </div>
            <div>
                <label for="salida">Salida</label>
                <input
                    type="time"
                    id="salida"
                    name="salida"
                    value="<?= $registro['salida'] ? h(substr((string) $registro['salida'], 0, 5)) : '' ?>"
                >
            </div>
            <div>
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <?php foreach (['OK', 'OBSERVADO', 'INCOMPLETO', 'ERROR'] as $e): ?>
                        <option value="<?= $e ?>" <?= $registro['estado'] === $e ? 'selected' : '' ?>><?= $e ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Total horas calculadas</label>
                <input
                    type="text"
                    value="<?= h((string) $registro['total_horas']) ?>"
                    readonly
                    style="background:var(--color-surface-3);"
                >
            </div>
            <div class="full">
                <label for="observacion">Observación</label>
                <textarea id="observacion" name="observacion"><?= h($registro['observacion']) ?></textarea>
            </div>
        </div>

        <div class="actions">
            <button type="submit">Guardar cambios</button>
            <a href="<?= h($returnUrl) ?>" class="btn btn-secondary">Volver</a>
        </div>
    </form>
</section>
