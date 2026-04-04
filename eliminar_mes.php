<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/auth.php';

date_default_timezone_set('America/Santiago');
$pdo = db();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mes_seleccionado = isset($_POST['mes']) ? trim($_POST['mes']) : '';
    $confirmacion = isset($_POST['confirmacion']) ? true : false;

    if ($mes_seleccionado === '') {
        $error = 'Debes seleccionar un mes.';
    } elseif (!$confirmacion) {
        $error = 'Debes marcar la casilla de confirmación para eliminar los datos.';
    } else {
        // Calcular el primer y último día del mes
        $fecha_inicio = $mes_seleccionado . '-01';
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

        try {
            $pdo->beginTransaction();

            // 1. Eliminar de marcaciones_resumen
            $stmtResumen = $pdo->prepare("DELETE FROM marcaciones_resumen WHERE fecha >= :inicio AND fecha <= :fin");
            $stmtResumen->execute(array(':inicio' => $fecha_inicio, ':fin' => $fecha_fin));
            $eliminados_resumen = $stmtResumen->rowCount();

            // 2. Eliminar de marcaciones (registros brutos)
            $stmtMarcas = $pdo->prepare("DELETE FROM marcaciones WHERE fecha >= :inicio AND fecha <= :fin");
            $stmtMarcas->execute(array(':inicio' => $fecha_inicio, ':fin' => $fecha_fin));
            $eliminados_brutos = $stmtMarcas->rowCount();

            // 3. Eliminar el historial de la tabla marcaciones_importaciones asociado al periodo
            // Apagamos FK checks para evitar conflictos si hay registros fuera de fecha amarrados
            $pdo->exec("SET SESSION foreign_key_checks=0");
            $stmtImportaciones = $pdo->prepare("DELETE FROM marcaciones_importaciones WHERE periodo = :mes");
            $stmtImportaciones->execute(array(':mes' => $mes_seleccionado));
            $eliminados_importaciones = $stmtImportaciones->rowCount();
            $pdo->exec("SET SESSION foreign_key_checks=1");

            $pdo->commit();

            if ($eliminados_resumen == 0 && $eliminados_brutos == 0 && $eliminados_importaciones == 0) {
                $mensaje = 'No se encontraron registros para el mes de ' . $mes_seleccionado . '.';
            } else {
                $mensaje = "¡Éxito! Se eliminaron $eliminados_brutos marcaciones brutas, $eliminados_resumen registros de resumen y $eliminados_importaciones registros de importación para el mes $mes_seleccionado.";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ocurrió un error al intentar eliminar los registros: ' . $e->getMessage();
        }
    }
}

// Obtener la lista del historial de importaciones para mostrar en la tabla
$importaciones = [];
try {
    $stmtLista = $pdo->query("SELECT id, nombre_archivo, periodo, total_lineas, total_insertadas, observacion FROM marcaciones_importaciones ORDER BY id DESC");
    $importaciones = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Si la tabla no existe o hay algún error, capturamos el mensaje silenciosamente para no romper la pantalla
    $errorLista = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Eliminar Marcaciones por Mes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *{box-sizing:border-box}
        body {
            margin: 0;
            font-family: 'Figtree', Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .main-scroll {
            flex: 1;
            overflow-y: auto;
            width: 100%;
        }
        .wrap { max-width: 900px; margin: 0 auto; padding: 24px; }
        .card{
            background:#fff; border-radius:16px; padding:24px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            margin-bottom: 24px;
        }
        h1{ margin:0 0 12px; color: #991b1b; }
        h2{ margin:0 0 12px; color: #1f2937; font-size: 20px;}
        p{ margin:0 0 16px; color:#4b5563; }
        .alert{ padding:12px 14px; border-radius:10px; margin-bottom:16px; }
        .ok{ background:#dcfce7; color:#166534; }
        .err{ background:#fee2e2; color:#991b1b; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px; }
        input[type="month"] {
            width: 100%; padding: 12px; border: 1px solid #d1d5db;
            border-radius: 10px; font-size: 14px; max-width: 300px;
        }
        .checkbox-group {
            display: flex; align-items: flex-start; gap: 10px;
            background: #fef2f2; padding: 16px; border-radius: 10px;
            border: 1px solid #fca5a5;
        }
        .checkbox-group input { width: 18px; height: 18px; margin-top: 2px; }
        .checkbox-group label { margin: 0; color: #991b1b; font-weight: normal; }
        .btn-danger {
            border: 0; background: #dc2626; color: #fff;
            padding: 12px 18px; border-radius: 10px; cursor: pointer;
            font-size: 15px; font-weight: bold; transition: 0.2s;
        }
        .btn-danger:hover { background: #b91c1c; }

        /* Estilos para la tabla de historial */
        .table-wrap { overflow-x: auto; margin-top: 16px; border: 1px solid #e5e7eb; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        th { background: #f9fafb; font-weight: bold; color: #4b5563; }
        tr:hover { background: #f9fafb; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #e5e7eb; color: #374151; }
        .badge.blue { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
<div class="main-scroll">
<div class="wrap">
    
    <div class="card">
        <h1>Eliminar marcaciones de un mes</h1>
        <p>Esta herramienta borrará de forma permanente <strong>todos los registros, resúmenes calculados y el historial de importación</strong> del mes seleccionado. Úsala para limpiar datos basura generados por pruebas o importaciones erróneas.</p>

        <?php if ($mensaje !== ''): ?>
            <div class="alert ok"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert err"><?php echo $error; ?></div>
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
    </div>

    <div class="card">
        <h2>Historial de Archivos Importados</h2>
        <p>Lista de archivos procesados y guardados en la tabla de registros. Al eliminar un mes en el formulario superior, se eliminará también su registro de esta lista.</p>
        
        <?php if (isset($errorLista)): ?>
            <div class="alert err">Error al cargar la tabla de importaciones: <?php echo htmlspecialchars($errorLista); ?></div>
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
                                <td><?php echo htmlspecialchars($imp['id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($imp['nombre_archivo']); ?></strong></td>
                                <td>
                                    <?php if (!empty($imp['periodo'])): ?>
                                        <span class="badge blue"><?php echo htmlspecialchars($imp['periodo']); ?></span>
                                    <?php else: ?>
                                        <span class="badge">Sin periodo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($imp['total_lineas'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($imp['total_insertadas'], 0, ',', '.'); ?></td>
                                <td style="color: #6b7280;"><?php echo htmlspecialchars($imp['observacion']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 16px; color: #6b7280; font-style: italic; background:#f9fafb;">
                        No hay registros de importaciones guardados en el sistema actualmente.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</div>
</body>
</html>