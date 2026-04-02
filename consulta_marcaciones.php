<?php
session_start();
require_once __DIR__ . '/inc/db.php';

date_default_timezone_set('America/Santiago');

$pdo = db();

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalizar_rut($rut){
    $rut = trim((string)$rut);
    $rut = strtoupper($rut);
    $rut = str_replace(array('.', '-', ' '), '', $rut);
    return $rut;
}

function rut_cuerpo($rut){
    $rut = normalizar_rut($rut);
    if (strlen($rut) < 2) {
        return '';
    }
    return substr($rut, 0, -1);
}

function rut_dv($rut){
    $rut = normalizar_rut($rut);
    if (strlen($rut) < 2) {
        return '';
    }
    return substr($rut, -1);
}

function validar_rut($rut){
    $rut = normalizar_rut($rut);

    if (!preg_match('/^[0-9]+[0-9K]$/', $rut)) {
        return false;
    }

    $cuerpo = rut_cuerpo($rut);
    $dv = rut_dv($rut);

    $suma = 0;
    $multiplo = 2;

    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += intval($cuerpo[$i]) * $multiplo;
        $multiplo++;
        if ($multiplo > 7) {
            $multiplo = 2;
        }
    }

    $resto = $suma % 11;
    $calc = 11 - $resto;

    if ($calc == 11) {
        $esperado = '0';
    } elseif ($calc == 10) {
        $esperado = 'K';
    } else {
        $esperado = (string)$calc;
    }

    return $dv === $esperado;
}

function nombre_dia_es($fechaYmd){
    $dias = array(
        'Sunday' => 'Domingo',
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado'
    );

    $diaIngles = date('l', strtotime($fechaYmd));
    return isset($dias[$diaIngles]) ? $dias[$diaIngles] : '';
}

function formatear_rut($rut){
    $rut = normalizar_rut($rut);
    if (strlen($rut) < 2) {
        return $rut;
    }

    $cuerpo = substr($rut, 0, -1);
    $dv = substr($rut, -1);

    $cuerpoInvertido = strrev($cuerpo);
    $partes = str_split($cuerpoInvertido, 3);
    $cuerpoFormateado = strrev(implode('.', $partes));

    return $cuerpoFormateado . '-' . $dv;
}

$error = '';
$rut = isset($_GET['rut']) ? trim($_GET['rut']) : '';
$periodo = isset($_GET['periodo']) ? trim($_GET['periodo']) : '';

$funcionario = null;
$resumenes = array();

if ($rut !== '') {
    if (!validar_rut($rut)) {
        $error = 'El RUT ingresado no es válido.';
    } else {
        $rut_base = rut_cuerpo($rut);

        $sqlFuncionario = "
            SELECT nombre, dpto, numero, rut_base
            FROM marcaciones_resumen
            WHERE rut_base = :rut_base
            ORDER BY fecha DESC
            LIMIT 1
        ";
        $stmtFuncionario = $pdo->prepare($sqlFuncionario);
        $stmtFuncionario->execute(array(':rut_base' => $rut_base));
        $funcionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

        if (!$funcionario) {
            $error = 'No se encontraron marcaciones para el RUT consultado.';
        } else {
            $where = array("mr.rut_base = :rut_base");
            $params = array(':rut_base' => $rut_base);

            if ($periodo !== '') {
                $where[] = "DATE_FORMAT(mr.fecha, '%Y-%m') = :periodo";
                $params[':periodo'] = $periodo;
            }

            $sqlResumen = "
                SELECT
                    mr.id,
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
                WHERE " . implode(' AND ', $where) . "
                ORDER BY mr.fecha DESC
            ";

            $stmtResumen = $pdo->prepare($sqlResumen);
            $stmtResumen->execute($params);
            $resumenes = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);

            $stmtDetalle = $pdo->prepare("
                SELECT hora
                FROM marcaciones
                WHERE rut_base = :rut_base
                  AND fecha = :fecha
                ORDER BY hora ASC, id ASC
            ");

            foreach ($resumenes as $k => $r) {
                $stmtDetalle->execute(array(
                    ':rut_base' => $rut_base,
                    ':fecha' => $r['fecha']
                ));
                $resumenes[$k]['detalle_marcaciones'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consulta de marcaciones</title>
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
            max-width:1450px;
            margin:0 auto;
            padding:24px;
        }
        .card{
            background:#fff;
            border-radius:16px;
            padding:24px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            margin-bottom:20px;
        }
        h1{
            margin:0 0 18px;
            font-size:28px;
        }
        .filters{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:8px;
        }
        .filters input[type="text"],
        .filters input[type="month"]{
            padding:12px 14px;
            border:1px solid #d1d5db;
            border-radius:10px;
            font-size:14px;
        }
        .filters input[type="text"]{
            min-width:260px;
            flex:1 1 260px;
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
        .alert{
            padding:12px 14px;
            border-radius:10px;
            margin-top:12px;
        }
        .err{
            background:#fee2e2;
            color:#991b1b;
        }
        .info-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
            gap:14px;
        }
        .info-box{
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:14px;
        }
        .info-box strong{
            display:block;
            font-size:12px;
            color:#6b7280;
            text-transform:uppercase;
            margin-bottom:6px;
        }
        .table-wrap{
            overflow-x:auto;
        }
        table{
            width:100%;
            border-collapse:collapse;
            min-width:1300px;
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
        .empty{
            padding:22px;
            text-align:center;
            color:#6b7280;
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
<div class="wrap">

    <div class="card">
        <h1>Consulta de marcaciones</h1>

        <form method="get" class="filters">
            <input
                type="text"
                name="rut"
                placeholder="Ingresa RUT, ej: 17.520.205-0"
                value="<?php echo h($rut); ?>"
            >

            <input
                type="month"
                name="periodo"
                value="<?php echo h($periodo); ?>"
            >

            <button type="submit">Consultar</button>
            <a class="secondary" href="consulta_marcaciones.php">Limpiar</a>
        </form>

        <?php if ($error !== ''): ?>
            <div class="alert err"><?php echo h($error); ?></div>
        <?php endif; ?>
    </div>

    <?php if ($funcionario): ?>
        <div class="card">
            <div class="info-grid">
                <div class="info-box">
                    <strong>Funcionario</strong>
                    <div><?php echo h($funcionario['nombre']); ?></div>
                </div>
                <div class="info-box">
                    <strong>Departamento</strong>
                    <div><?php echo h($funcionario['dpto']); ?></div>
                </div>
                <div class="info-box">
                    <strong>No.</strong>
                    <div><?php echo h($funcionario['numero']); ?></div>
                </div>
                <div class="info-box">
                    <strong>RUT consultado</strong>
                    <div><?php echo h(formatear_rut($rut)); ?></div>
                </div>
                <div class="info-box">
                    <strong>Total días encontrados</strong>
                    <div><?php echo count($resumenes); ?></div>
                </div>
            </div>
        </div>

        <div class="card">
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
                                <!--<td><?php echo h(date('d/m/Y', strtotime($r['fecha']))); ?></td>-->
                                <td>
    <strong><?php echo h(nombre_dia_es($r['fecha'])); ?></strong><br>
    <?php echo h(date('d/m/Y', strtotime($r['fecha']))); ?>
</td>

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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>