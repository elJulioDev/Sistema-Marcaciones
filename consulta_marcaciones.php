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
        body {
            margin: 0;
            font-family: 'Figtree', Arial, Helvetica, sans-serif; /* Unificamos fuente */
            background: #f4f7fb;
            color: #1f2937;
            
            /* -- MAGIA PARA EL SCROLL DEBAJO DEL NAVBAR -- */
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Congela la ventana principal */
        }

        /* Nuevo contenedor que tendrá la barra de desplazamiento */
        .main-scroll {
            flex: 1;
            overflow-y: auto;
            width: 100%;
        }

        .wrap {
            /* (Mantén el contenido que ya tenías en tu clase wrap) */
            max-width: 1500px; /* (o el ancho que ya tengas en cada archivo) */
            margin: 0 auto;
            padding: 24px;
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

        /* Ocultar el logo en la vista normal de la web */
        .print-logo {
            display: none;
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

        /* --- ESTILOS PARA IMPRESIÓN / REPORTE TIPO EXCEL (VERTICAL) --- */
        @media print {
            /* Configurar página en vertical (portrait) */
            @page { size: portrait; margin: 15mm; }
            
            /* 1. Limpiar fondos y resetear márgenes */
            body { 
                background: #ffffff !important; 
                color: #000000 !important;
                margin: 0 !important; 
                padding: 0 !important;
                height: auto !important; 
                overflow: visible !important; 
            }
            
            /* 2. Ocultar todo lo interactivo y decorativo */
            .global-navbar, 
            .filters, 
            .btn-print,
            button,
            a { 
                display: none !important; 
            }
            
            /* 3. Eliminar el formato de las "tarjetas" (cards) */
            .main-scroll { overflow: visible !important; height: auto !important; }
            .wrap { padding: 0 !important; max-width: 100% !important; }
            .card { 
                box-shadow: none !important; 
                border: none !important; 
                padding: 0 !important; 
                margin-bottom: 20px !important; 
                background: transparent !important;
            }
            
            /* 4. Formatear la caja de información del empleado como un encabezado formal */
            .info-grid {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 20px !important;
                border: 2px solid #000 !important;
                padding: 10px 15px !important;
                position: relative !important; /* Crucial para posicionar el logo */
                padding-right: 120px !important; /* Deja un espacio vacío a la derecha para que el texto no pise el logo */
            }
            .print-logo {
                display: block !important; /* Hace visible el logo en el PDF */
                position: absolute !important;
                top: 50% !important;
                transform: translateY(-50%) !important; /* Lo centra verticalmente perfecto */
                right: 15px !important;
                max-height: 60px !important; /* Límite de alto para que no deforme la caja */
                max-width: 90px !important;
                object-fit: contain !important;
            }
            .info-box {
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
            }
            .info-box strong { 
                color: #000 !important; 
                font-size: 10pt !important; 
            }
            .info-box div { 
                color: #000 !important; 
                font-size: 11pt !important; 
                font-weight: bold !important;
            }

            /* 5. Transformar la tabla en un grid tipo Excel (Ajuste Vertical Perfecto) */
            .table-wrap { overflow-x: visible !important; }
            table {
                width: 100% !important;
                min-width: 0 !important; 
                max-width: 100% !important;
                border-collapse: collapse !important;
                border: 2px solid #000 !important;
                table-layout: fixed !important; /* Estricto control de anchos */
            }
            th, td {
                box-sizing: border-box !important; /* Evita que el padding sume ancho extra */
                border: 1px solid #000 !important;
                padding: 4px 6px !important; 
                font-size: 8pt !important; 
                color: #000 !important;
                background: #fff !important;
                /* Control estricto de textos largos para que no se salgan */
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                word-break: break-word !important;
                vertical-align: middle !important;
            }
            th {
                background-color: #e5e7eb !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* --- OCULTAR COLUMNA "EDITADO" EN EL PDF --- */
            th:nth-child(9), td:nth-child(9) { 
                display: none !important; 
            }

            /* --- NUEVA DISTRIBUCIÓN DE COLUMNAS (Solo 8 columnas activas = 100%) --- */
            th:nth-child(1), td:nth-child(1) { width: 14%; } /* Fecha */
            th:nth-child(2), td:nth-child(2) { width: 6%; text-align: center !important; } /* Cant. */
            th:nth-child(3), td:nth-child(3) { width: 16%; } /* Detalle marcaciones */
            th:nth-child(4), td:nth-child(4) { width: 7%; text-align: center !important; } /* Entrada */
            th:nth-child(5), td:nth-child(5) { width: 7%; text-align: center !important; } /* Salida */
            th:nth-child(6), td:nth-child(6) { width: 7%; text-align: center !important; } /* Total */
            th:nth-child(7), td:nth-child(7) { width: 13%; text-align: center !important; } /* Estado (Más grande para que quepa "INCOMPLETO" o "OBSERVADO") */
            th:nth-child(8), td:nth-child(8) { width: 30%; } /* Observación (Espacio restante) */

            /* 6. Quitar colores y formas de las insignias (Badges) y marcaciones */
            .badge {
                background: transparent !important;
                color: #000 !important;
                padding: 0 !important;
                border: none !important;
                font-weight: bold !important;
            }
            
            /* Convertir los cuadritos de marcaciones en texto separado por comas */
            .marcas {
                display: inline !important;
                gap: 0 !important;
            }
            .marca-item {
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
                display: inline !important;
                font-family: inherit !important;
            }
            .marca-item::after {
                content: ", "; /* Agrega una coma entre cada hora */
            }
            .marca-item:last-child::after {
                content: ""; /* Quita la coma de la última hora */
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

<div class="main-scroll">
<div class="wrap">

    <div class="card">
        <h1>Consulta de marcaciones</h1>

        <form method="get" class="filters" style="align-items: center;">
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

            <?php if ($funcionario): ?>
                <button type="button" class="btn-print" onclick="window.print()" style="margin-left: auto; background-color: #10b981; display: flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M6 9V2h12v7"></path>
                        <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Imprimir / Guardar PDF
                </button>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
            <div class="alert err"><?php echo h($error); ?></div>
        <?php endif; ?>
    </div>

    <?php if ($funcionario): ?>
        <div class="card">
            <div class="info-grid">
                <img src="static/img/logo.png" class="print-logo" alt="Logo Municipalidad">
                
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
</div>
</body>
</html>