<?php
session_start();
require_once __DIR__ . '/inc/db.php';

date_default_timezone_set('America/Santiago');

$pdo = db();

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$periodo = isset($_GET['periodo']) ? trim($_GET['periodo']) : '';

$where = array();
$params = array();

$where[] = "mr.estado IN ('OBSERVADO','INCOMPLETO','ERROR')";

if ($filtroEstado !== '' && in_array($filtroEstado, array('OBSERVADO','INCOMPLETO','ERROR','OK'))) {
    array_pop($where);
    $where[] = "mr.estado = :estado";
    $params[':estado'] = $filtroEstado;
}

if ($q !== '') {
    $where[] = "(mr.nombre LIKE :q1
        OR mr.numero LIKE :q2
        OR mr.rut_base LIKE :q3
        OR mr.dpto LIKE :q4
        OR mr.observacion LIKE :q5)";
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
    $params[':q4'] = '%' . $q . '%';
    $params[':q5'] = '%' . $q . '%';
}

if ($periodo !== '') {
    $where[] = "DATE_FORMAT(mr.fecha, '%Y-%m') = :periodo";
    $params[':periodo'] = $periodo;
}

$sql = "
    SELECT
        mr.id,
        mr.rut_base,
        mr.numero,
        mr.nombre,
        mr.dpto,
        mr.fecha,
        mr.entrada,
        mr.salida,
        mr.total_horas,
        mr.cantidad_marcaciones,
        mr.estado,
        mr.observacion,
        mr.editado_manual,
        mr.updated_at
    FROM marcaciones_resumen mr
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY mr.fecha DESC, mr.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtDetalle = $pdo->prepare("
    SELECT hora
    FROM marcaciones
    WHERE rut_base = :rut_base
      AND fecha = :fecha
    ORDER BY hora ASC, id ASC
");

foreach ($rows as $k => $r) {
    $stmtDetalle->execute(array(
        ':rut_base' => $r['rut_base'],
        ':fecha' => $r['fecha']
    ));
    $rows[$k]['detalle_marcaciones'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Observaciones de marcaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:#f4f7fb;
            color:#1f2937;
        }
        .wrap{
            max-width:1500px;
            margin:0 auto;
            padding:24px;
        }
        .card{
            background:#fff;
            border-radius:16px;
            padding:24px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }
        h1{
            margin:0 0 18px;
            font-size:28px;
        }
        .topbar{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:18px;
        }
        .topbar a{
            text-decoration:none;
            padding:10px 14px;
            border-radius:10px;
            background:#e5e7eb;
            color:#111827;
            font-size:14px;
        }
        .topbar a.active{
            background:#2563eb;
            color:#fff;
        }
        .filters{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:18px;
        }
        .filters input[type="text"]{
            min-width:240px;
            flex:1 1 240px;
            padding:12px 14px;
            border:1px solid #d1d5db;
            border-radius:10px;
            font-size:14px;
        }
        .filters input[type="month"]{
            padding:12px 14px;
            border:1px solid #d1d5db;
            border-radius:10px;
            font-size:14px;
        }
        .filters button,
        .filters a{
            border:0;
            background:#2563eb;
            color:#fff;
            padding:12px 16px;
            border-radius:10px;
            text-decoration:none;
            cursor:pointer;
            font-size:14px;
        }
        .filters a.secondary{
            background:#6b7280;
        }
        .table-wrap{
            overflow-x:auto;
        }
        table{
            width:100%;
            border-collapse:collapse;
            min-width:1450px;
        }
        th, td{
            padding:10px;
            border-bottom:1px solid #e5e7eb;
            text-align:left;
            vertical-align:top;
            font-size:14px;
        }
        th{
            background:#f9fafb;
        }
        .badge{
            display:inline-block;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:bold;
            white-space:nowrap;
        }
        .badge-ok{background:#dcfce7;color:#166534}
        .badge-obs{background:#dbeafe;color:#1d4ed8}
        .badge-inc{background:#fef3c7;color:#92400e}
        .badge-err{background:#fee2e2;color:#991b1b}
        .small{
            color:#6b7280;
            font-size:12px;
        }
        .btn-editar{
            display:inline-block;
            padding:8px 12px;
            background:#2563eb;
            color:#fff;
            text-decoration:none;
            border-radius:8px;
            font-size:14px;
        }
        .btn-editar:hover{
            background:#1d4ed8;
        }
        .empty{
            padding:22px;
            text-align:center;
            color:#6b7280;
        }
        .marcas{
            display:flex;
            flex-direction:column;
            gap:4px;
        }
        .marca-item{
            display:inline-block;
            background:#f3f4f6;
            border:1px solid #e5e7eb;
            border-radius:8px;
            padding:5px 8px;
            width:max-content;
            min-width:58px;
            text-align:center;
        }
        @media (max-width: 768px){
            .wrap{
                padding:14px;
            }
            .card{
                padding:16px;
            }
            .filters input[type="text"]{
                min-width:100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
<div class="wrap">
    <div class="card">
        <h1>Observaciones de marcaciones</h1>

        <div class="topbar">
            <a href="observaciones_marcaciones.php" class="<?php echo ($filtroEstado === '' ? 'active' : ''); ?>">Todos</a>
            <a href="observaciones_marcaciones.php?estado=OBSERVADO<?php echo ($periodo !== '' ? '&periodo=' . urlencode($periodo) : ''); ?>" class="<?php echo ($filtroEstado === 'OBSERVADO' ? 'active' : ''); ?>">Observados</a>
            <a href="observaciones_marcaciones.php?estado=INCOMPLETO<?php echo ($periodo !== '' ? '&periodo=' . urlencode($periodo) : ''); ?>" class="<?php echo ($filtroEstado === 'INCOMPLETO' ? 'active' : ''); ?>">Incompletos</a>
            <a href="observaciones_marcaciones.php?estado=ERROR<?php echo ($periodo !== '' ? '&periodo=' . urlencode($periodo) : ''); ?>" class="<?php echo ($filtroEstado === 'ERROR' ? 'active' : ''); ?>">Errores</a>
            <a href="observaciones_marcaciones.php?estado=OK<?php echo ($periodo !== '' ? '&periodo=' . urlencode($periodo) : ''); ?>" class="<?php echo ($filtroEstado === 'OK' ? 'active' : ''); ?>">OK</a>
        </div>

        <form method="get" class="filters">
            <?php if ($filtroEstado !== ''): ?>
                <input type="hidden" name="estado" value="<?php echo h($filtroEstado); ?>">
            <?php endif; ?>

            <input
                type="text"
                name="q"
                placeholder="Buscar por nombre, número, rut base, dpto u observación"
                value="<?php echo h($q); ?>"
            >

            <input
                type="month"
                name="periodo"
                value="<?php echo h($periodo); ?>"
            >

            <button type="submit">Buscar</button>

            <a class="secondary" href="observaciones_marcaciones.php<?php echo ($filtroEstado !== '' ? '?estado=' . urlencode($filtroEstado) : ''); ?>">Limpiar</a>
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
                            <td><?php echo h(date('d/m/Y', strtotime($r['fecha']))); ?></td>
                            <td>
                                <strong><?php echo h($r['nombre']); ?></strong><br>
                                <span class="small">RUT base: <?php echo h($r['rut_base']); ?></span>
                            </td>
                            <td><?php echo h($r['dpto']); ?></td>
                            <td><?php echo h($r['numero']); ?></td>
                            <td><?php echo (int)$r['cantidad_marcaciones']; ?></td>
                            <td>
                                <div class="marcas">
                                    <?php if (!empty($r['detalle_marcaciones'])): ?>
                                        <?php foreach ($r['detalle_marcaciones'] as $m): ?>
                                            <span class="marca-item"><?php echo h(substr($m['hora'], 0, 5)); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="small">Sin detalle</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo ($r['entrada'] ? h(substr($r['entrada'], 0, 5)) : '-'); ?></td>
                            <td><?php echo ($r['salida'] ? h(substr($r['salida'], 0, 5)) : '-'); ?></td>
                            <td><?php echo ($r['total_horas'] ? h(substr($r['total_horas'], 0, 5)) : '-'); ?></td>
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
                            <td><?php echo nl2br(h($r['observacion'])); ?></td>
                            <td>
                                <?php if ((int)$r['editado_manual'] === 1): ?>
                                    <span class="small">
                                        Sí<br><?php echo h($r['updated_at']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="small">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="btn-editar" href="editar_marcacion_resumen.php?id=<?php echo (int)$r['id']; ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>