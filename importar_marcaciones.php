<?php
require_once __DIR__ . '/inc/db.php';
session_start();
date_default_timezone_set('America/Santiago');

$pdo = db();

$mensaje = '';
$error = '';
$resumen = array(
    'leidos' => 0,
    'insertados' => 0,
    'duplicados' => 0,
    'invalidos' => 0,
    'resumen_recalculado' => 0
);

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function limpiar_numero($valor){
    $valor = trim((string)$valor);
    $valor = strtoupper($valor);
    return preg_replace('/[^0-9K]/', '', $valor);
}

function obtener_rut_base($numero){
    return preg_replace('/[^0-9]/', '', (string)$numero);
}

function parsear_linea_tabulada($linea){
    $linea = trim((string)$linea);
    if ($linea === '') {
        return false;
    }

    $partes = preg_split("/\t+/", $linea);
    if (count($partes) < 4) {
        return false;
    }

    return array(
        'dpto'       => trim($partes[0]),
        'nombre'     => trim($partes[1]),
        'numero'     => trim($partes[2]),
        'fecha_hora' => trim($partes[3])
    );
}

function minutos_a_time($minutos){
    $horas = floor($minutos / 60);
    $mins = $minutos % 60;
    return sprintf('%02d:%02d:00', $horas, $mins);
}

function recalcular_resumen($pdo){
    $sql = "SELECT 
                rut_base,
                numero,
                nombre,
                dpto,
                fecha,
                COUNT(*) AS cantidad_marcaciones
            FROM marcaciones
            GROUP BY rut_base, numero, nombre, dpto, fecha
            ORDER BY fecha ASC, nombre ASC";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $stmtMarcas = $pdo->prepare("
        SELECT hora
        FROM marcaciones
        WHERE rut_base = :rut_base
          AND fecha = :fecha
        ORDER BY hora ASC, id ASC
    ");

    $upsert = $pdo->prepare("
        INSERT INTO marcaciones_resumen
        (rut_base, numero, nombre, dpto, fecha, entrada, salida, total_horas, cantidad_marcaciones, estado, observacion, editado_manual)
        VALUES
        (:rut_base, :numero, :nombre, :dpto, :fecha, :entrada, :salida, :total_horas, :cantidad_marcaciones, :estado, :observacion, 0)
        ON DUPLICATE KEY UPDATE
            numero = VALUES(numero),
            nombre = VALUES(nombre),
            dpto = VALUES(dpto),
            cantidad_marcaciones = VALUES(cantidad_marcaciones),
            entrada = IF(editado_manual = 1, entrada, VALUES(entrada)),
            salida = IF(editado_manual = 1, salida, VALUES(salida)),
            total_horas = IF(editado_manual = 1, total_horas, VALUES(total_horas)),
            estado = IF(editado_manual = 1, estado, VALUES(estado)),
            observacion = IF(editado_manual = 1, observacion, VALUES(observacion))
    ");

    $total = 0;

    foreach ($rows as $r) {
        $stmtMarcas->execute(array(
            ':rut_base' => $r['rut_base'],
            ':fecha' => $r['fecha']
        ));
        $marcas = $stmtMarcas->fetchAll(PDO::FETCH_ASSOC);

        $cantidad = count($marcas);
        $entrada = null;
        $salida = null;
        $totalHoras = null;
        $estado = 'OK';
        $observacion = '';

        if ($cantidad > 0) {
            $entrada = $marcas[0]['hora'];
        }

        if ($cantidad === 1) {
            $estado = 'INCOMPLETO';
            $observacion = 'Solo existe una marcación en el día.';
        } elseif ($cantidad >= 2) {
            $salida = $marcas[$cantidad - 1]['hora'];

            $ts1 = strtotime($r['fecha'] . ' ' . $entrada);
            $ts2 = strtotime($r['fecha'] . ' ' . $salida);
            $difMin = intval(($ts2 - $ts1) / 60);

            if ($difMin < 0) {
                $estado = 'ERROR';
                $observacion = 'La salida calculada es anterior a la entrada.';
                $totalHoras = null;
            } else {
                $totalHoras = minutos_a_time($difMin);

                if ($cantidad > 2) {
                    $estado = 'OBSERVADO';
                    $observacion = 'Día con ' . $cantidad . ' marcaciones. Revisar detalle del día.';
                }
            }
        }

        $upsert->execute(array(
            ':rut_base' => $r['rut_base'],
            ':numero' => $r['numero'],
            ':nombre' => $r['nombre'],
            ':dpto' => $r['dpto'],
            ':fecha' => $r['fecha'],
            ':entrada' => $entrada,
            ':salida' => $salida,
            ':total_horas' => $totalHoras,
            ':cantidad_marcaciones' => $cantidad,
            ':estado' => $estado,
            ':observacion' => $observacion
        ));

        $total++;
    }

    return $total;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $periodo = isset($_POST['periodo']) ? trim($_POST['periodo']) : '';
    $observacion_importacion = isset($_POST['observacion']) ? trim($_POST['observacion']) : '';
    $creado_por = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

    if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
        $error = 'Debes seleccionar un archivo.';
    } else {
        $nombreArchivo = isset($_FILES['archivo']['name']) ? $_FILES['archivo']['name'] : '';
        $tmp = $_FILES['archivo']['tmp_name'];
        $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

        if (!in_array($ext, array('txt', 'csv'))) {
            $error = 'Solo se permiten archivos TXT o CSV.';
        } else {
            $lineas = file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if (!$lineas || count($lineas) <= 1) {
                $error = 'El archivo está vacío o no contiene datos válidos.';
            } else {
                $pdo->beginTransaction();

                try {
                    $insertImportacion = $pdo->prepare("
                        INSERT INTO marcaciones_importaciones
                        (nombre_archivo, periodo, observacion, total_lineas, total_insertadas, total_duplicadas, total_invalidas, creado_por)
                        VALUES
                        (:nombre_archivo, :periodo, :observacion, 0, 0, 0, 0, :creado_por)
                    ");

                    $insertImportacion->execute(array(
                        ':nombre_archivo' => $nombreArchivo,
                        ':periodo' => ($periodo !== '' ? $periodo : null),
                        ':observacion' => ($observacion_importacion !== '' ? $observacion_importacion : null),
                        ':creado_por' => ($creado_por > 0 ? $creado_por : null)
                    ));

                    $id_importacion = (int)$pdo->lastInsertId();

                    $insert = $pdo->prepare("
                        INSERT INTO marcaciones
                        (id_importacion, dpto, nombre, numero, rut_base, fecha_hora, fecha, hora, hash_registro)
                        VALUES
                        (:id_importacion, :dpto, :nombre, :numero, :rut_base, :fecha_hora, :fecha, :hora, :hash_registro)
                    ");

                    $primera = true;

                    foreach ($lineas as $linea) {
                        if ($primera) {
                            $primera = false;
                            continue;
                        }

                        $resumen['leidos']++;

                        $fila = parsear_linea_tabulada($linea);
                        if ($fila === false) {
                            $resumen['invalidos']++;
                            continue;
                        }

                        $dpto = $fila['dpto'];
                        $nombre = $fila['nombre'];
                        $numero = limpiar_numero($fila['numero']);
                        $rut_base = obtener_rut_base($numero);
                        $fechaHoraTexto = $fila['fecha_hora'];

                        if ($dpto === '' || $nombre === '' || $numero === '' || $rut_base === '' || $fechaHoraTexto === '') {
                            $resumen['invalidos']++;
                            continue;
                        }

                        $dt = DateTime::createFromFormat('d/m/Y H:i', $fechaHoraTexto);
                        if (!$dt) {
                            $resumen['invalidos']++;
                            continue;
                        }

                        $fecha_hora = $dt->format('Y-m-d H:i:s');
                        $fecha = $dt->format('Y-m-d');
                        $hora = $dt->format('H:i:s');

                        $hash = md5(
                            strtoupper($dpto) . '|' .
                            strtoupper($nombre) . '|' .
                            $numero . '|' .
                            $fecha_hora
                        );

                        try {
                            $insert->execute(array(
                                ':id_importacion' => $id_importacion,
                                ':dpto' => $dpto,
                                ':nombre' => $nombre,
                                ':numero' => $numero,
                                ':rut_base' => $rut_base,
                                ':fecha_hora' => $fecha_hora,
                                ':fecha' => $fecha,
                                ':hora' => $hora,
                                ':hash_registro' => $hash
                            ));
                            $resumen['insertados']++;
                        } catch (PDOException $e) {
                            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                                $resumen['duplicados']++;
                            } else {
                                throw $e;
                            }
                        }
                    }

                    $resumen['resumen_recalculado'] = recalcular_resumen($pdo);

                    $updateImportacion = $pdo->prepare("
                        UPDATE marcaciones_importaciones
                        SET total_lineas = :total_lineas,
                            total_insertadas = :total_insertadas,
                            total_duplicadas = :total_duplicadas,
                            total_invalidas = :total_invalidas
                        WHERE id = :id
                    ");

                    $updateImportacion->execute(array(
                        ':total_lineas' => $resumen['leidos'],
                        ':total_insertadas' => $resumen['insertados'],
                        ':total_duplicadas' => $resumen['duplicados'],
                        ':total_invalidas' => $resumen['invalidos'],
                        ':id' => $id_importacion
                    ));

                    $pdo->commit();
                    $mensaje = 'Archivo importado correctamente. La carga quedó registrada en el historial.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error al importar: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Importar marcaciones</title>
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
            max-width:1000px;
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
            margin:0 0 12px;
        }
        p{
            margin:0 0 16px;
            color:#4b5563;
        }
        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));
            gap:14px;
            margin-bottom:16px;
        }
        label{
            display:block;
            font-weight:bold;
            margin-bottom:6px;
            font-size:14px;
        }
        input[type="text"],
        input[type="file"],
        textarea{
            width:100%;
            padding:12px;
            border:1px solid #d1d5db;
            border-radius:10px;
            font-size:14px;
        }
        textarea{
            min-height:110px;
            resize:vertical;
        }
        .full{
            grid-column:1 / -1;
        }
        .actions{
            margin-top:18px;
        }
        button{
            border:0;
            background:#2563eb;
            color:#fff;
            padding:12px 18px;
            border-radius:10px;
            cursor:pointer;
        }
        .alert{
            padding:12px 14px;
            border-radius:10px;
            margin-bottom:16px;
        }
        .ok{
            background:#dcfce7;
            color:#166534;
        }
        .err{
            background:#fee2e2;
            color:#991b1b;
        }
        table{
            width:100%;
            border-collapse:collapse;
            margin-top:20px;
        }
        th, td{
            padding:10px;
            border-bottom:1px solid #e5e7eb;
            text-align:left;
            font-size:14px;
        }
        th{
            background:#f9fafb;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
<div class="wrap">
    <div class="card">
        <h1>Importar archivo de marcaciones</h1>
        <p>Esta carga quedará guardada como historial independiente.</p>

        <?php if ($mensaje !== ''): ?>
            <div class="alert ok"><?php echo h($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert err"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="grid">
                <div>
                    <label for="periodo">Período</label>
                    <input
                        type="text"
                        id="periodo"
                        name="periodo"
                        placeholder="Ej: 2025-10"
                        value="<?php echo isset($_POST['periodo']) ? h($_POST['periodo']) : ''; ?>"
                    >
                </div>

                <div>
                    <label for="archivo">Archivo</label>
                    <input type="file" id="archivo" name="archivo" accept=".txt,.csv">
                </div>

                <div class="full">
                    <label for="observacion">Observación de la carga</label>
                    <textarea
                        id="observacion"
                        name="observacion"
                        placeholder="Ej: Marcaciones octubre 2025"
                    ><?php echo isset($_POST['observacion']) ? h($_POST['observacion']) : ''; ?></textarea>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Subir e importar</button>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Leídos</th>
                    <th>Insertados</th>
                    <th>Duplicados</th>
                    <th>Inválidos</th>
                    <th>Resumen recalculado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo (int)$resumen['leidos']; ?></td>
                    <td><?php echo (int)$resumen['insertados']; ?></td>
                    <td><?php echo (int)$resumen['duplicados']; ?></td>
                    <td><?php echo (int)$resumen['invalidos']; ?></td>
                    <td><?php echo (int)$resumen['resumen_recalculado']; ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>