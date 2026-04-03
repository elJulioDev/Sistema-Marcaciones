<?php
date_default_timezone_set('America/Santiago');

$host = 'localhost';
$db   = 'coltauco_RRHH';
$user = 'coltauco';
$pass = 'M.c0lt4uc0.66';
$charset = 'utf8mb4';


$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$opciones = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
);

try {
    $pdo = new PDO($dsn, $user, $pass, $opciones);
} catch (Exception $e) {
    die('Error de conexión: ' . $e->getMessage());
}

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function minutos_a_time($minutos){
    $horas = floor($minutos / 60);
    $mins = $minutos % 60;
    return sprintf('%02d:%02d:00', $horas, $mins);
}

function normalizar_hora($hora){
    $hora = trim((string)$hora);
    if ($hora === '') {
        return null;
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return false;
    }

    return $hora . ':00';
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rut = isset($_GET['rut']) ? trim($_GET['rut']) : '';
$fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';

if ($id <= 0 && ($rut === '' || $fecha === '')) {
    die('ID o parámetros no válidos.');
}

$mensaje = '';
$error = '';
$registro = null;

if ($id > 0) {
    // Si viene un ID directo, lo buscamos
    $stmt = $pdo->prepare("SELECT * FROM marcaciones_resumen WHERE id = :id LIMIT 1");
    $stmt->execute(array(':id' => $id));
    $registro = $stmt->fetch();
} else {
    // Si no hay ID, buscamos si ya se creó un registro para ese RUT y fecha
    $stmt = $pdo->prepare("SELECT * FROM marcaciones_resumen WHERE rut_base = :rut AND fecha = :fecha LIMIT 1");
    $stmt->execute(array(':rut' => $rut, ':fecha' => $fecha));
    $registro = $stmt->fetch();
    
    if ($registro) {
        $id = $registro['id'];
    } else {
        // No existe el registro (Faltó). Extraemos los datos básicos del empleado
        $stmtEmp = $pdo->prepare("SELECT nombre, dpto, numero FROM marcaciones_resumen WHERE rut_base = :rut ORDER BY fecha DESC LIMIT 1");
        $stmtEmp->execute(array(':rut' => $rut));
        $emp = $stmtEmp->fetch();
        
        if ($emp) {
            // Creamos un registro virtual en memoria para mostrar en el formulario
            $registro = array(
                'id' => 0,
                'rut_base' => $rut,
                'nombre' => $emp['nombre'],
                'dpto' => $emp['dpto'],
                'numero' => $emp['numero'],
                'fecha' => $fecha,
                'entrada' => null,
                'salida' => null,
                'total_horas' => null,
                'cantidad_marcaciones' => 0,
                'estado' => 'ERROR',
                'observacion' => '',
                'editado_manual' => 0
            );
        }
    }
}

if (!$registro) {
    die('Registro o empleado no encontrado.');
}

// --- NUEVO CÓDIGO PARA EL BOTÓN VOLVER ---
$return_url = 'observaciones_marcaciones.php';
if (isset($_POST['return_url']) && !empty($_POST['return_url'])) {
    $return_url = $_POST['return_url'];
} elseif (isset($_GET['return_url']) && !empty($_GET['return_url'])) {
    $return_url = $_GET['return_url'];
} elseif (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'editar_marcacion_resumen.php') === false) {
    $return_url = $_SERVER['HTTP_REFERER'];
}
// -----------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entrada_post = isset($_POST['entrada']) ? trim($_POST['entrada']) : '';
    $salida_post = isset($_POST['salida']) ? trim($_POST['salida']) : '';
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'OK';
    $observacion = isset($_POST['observacion']) ? trim($_POST['observacion']) : '';

    $entrada = normalizar_hora($entrada_post);
    $salida = normalizar_hora($salida_post);

    if ($entrada === false) {
        $error = 'La hora de entrada no tiene un formato válido.';
    } elseif ($salida === false) {
        $error = 'La hora de salida no tiene un formato válido.';
    } elseif (!in_array($estado, array('OK', 'OBSERVADO', 'INCOMPLETO', 'ERROR'))) {
        $error = 'El estado no es válido.';
    } else {
        $total_horas = null;

        if ($entrada !== null && $salida !== null) {
            $ts1 = strtotime('2000-01-01 ' . $entrada);
            $ts2 = strtotime('2000-01-01 ' . $salida);

            if ($ts2 < $ts1) {
                $estado = 'ERROR';
                if ($observacion === '') {
                    $observacion = 'La salida es anterior a la entrada.';
                }
            } else {
                $difMin = intval(($ts2 - $ts1) / 60);
                $total_horas = minutos_a_time($difMin);
            }
        } elseif ($entrada !== null && $salida === null) {
            if ($estado === 'OK') {
                $estado = 'INCOMPLETO';
            }
            $total_horas = null;
        } elseif ($entrada === null && $salida !== null) {
            $estado = 'ERROR';
            if ($observacion === '') {
                $observacion = 'Existe salida pero no entrada.';
            }
            $total_horas = null;
        }

        if ($id > 0) {
            // Si ya existe en la base de datos, lo actualizamos
            $update = $pdo->prepare("
                UPDATE marcaciones_resumen
                SET
                    entrada = :entrada,
                    salida = :salida,
                    total_horas = :total_horas,
                    estado = :estado,
                    observacion = :observacion,
                    editado_manual = 1
                WHERE id = :id
            ");

            $update->execute(array(
                ':entrada' => $entrada,
                ':salida' => $salida,
                ':total_horas' => $total_horas,
                ':estado' => $estado,
                ':observacion' => $observacion,
                ':id' => $id
            ));
        } else {
            // Si no existe (era una ausencia), insertamos un nuevo registro
            $insert = $pdo->prepare("
                INSERT INTO marcaciones_resumen
                (rut_base, numero, nombre, dpto, fecha, entrada, salida, total_horas, cantidad_marcaciones, estado, observacion, editado_manual)
                VALUES
                (:rut_base, :numero, :nombre, :dpto, :fecha, :entrada, :salida, :total_horas, 0, :estado, :observacion, 1)
            ");
            
            $insert->execute(array(
                ':rut_base' => $registro['rut_base'],
                ':numero' => $registro['numero'],
                ':nombre' => $registro['nombre'],
                ':dpto' => $registro['dpto'],
                ':fecha' => $registro['fecha'],
                ':entrada' => $entrada,
                ':salida' => $salida,
                ':total_horas' => $total_horas,
                ':estado' => $estado,
                ':observacion' => $observacion
            ));
            
            // Recuperamos el ID recién creado para que pueda volver a cargar correctamente la vista
            $id = $pdo->lastInsertId();
            $registro['id'] = $id;
        }

        $stmt = $pdo->prepare("SELECT * FROM marcaciones_resumen WHERE id = :id LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $registro = $stmt->fetch();

        $mensaje = 'Marcación resumen actualizada correctamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar marcación resumen</title>
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
            max-width:900px;
            margin:0 auto;
            padding:24px;
        }
        .card{
            background:#fff;
            border-radius:16px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            padding:24px;
        }
        h1{
            margin:0 0 18px;
        }
        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
            gap:14px;
            margin-bottom:20px;
        }
        .info{
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:14px;
        }
        .info strong{
            display:block;
            font-size:12px;
            color:#6b7280;
            text-transform:uppercase;
            margin-bottom:6px;
        }
        .info span{
            font-size:16px;
            color:#111827;
        }
        .form-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
            gap:14px;
        }
        label{
            display:block;
            font-size:14px;
            font-weight:bold;
            margin-bottom:6px;
        }
        input[type="time"],
        select,
        textarea{
            width:100%;
            padding:12px;
            border:1px solid #d1d5db;
            border-radius:10px;
            font-size:14px;
        }
        textarea{
            min-height:120px;
            resize:vertical;
        }
        .full{
            grid-column:1 / -1;
        }
        .actions{
            margin-top:20px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        button,
        .btn{
            border:0;
            background:#2563eb;
            color:#fff;
            padding:12px 18px;
            border-radius:10px;
            text-decoration:none;
            cursor:pointer;
            display:inline-block;
        }
        .btn-secondary{
            background:#6b7280;
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
        .badge{
            display:inline-block;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:bold;
        }
        .badge-ok{background:#dcfce7;color:#166534}
        .badge-obs{background:#dbeafe;color:#1d4ed8}
        .badge-inc{background:#fef3c7;color:#92400e}
        .badge-err{background:#fee2e2;color:#991b1b}
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
<div class="wrap">
    <div class="card">
        <h1>Editar marcación resumen</h1>

        <?php if ($mensaje !== ''): ?>
            <div class="alert ok"><?php echo h($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert err"><?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="info">
                <strong>Funcionario</strong>
                <span><?php echo h($registro['nombre']); ?></span>
            </div>
            <div class="info">
                <strong>Departamento</strong>
                <span><?php echo h($registro['dpto']); ?></span>
            </div>
            <div class="info">
                <strong>No.</strong>
                <span><?php echo h($registro['numero']); ?></span>
            </div>
            <div class="info">
                <strong>Fecha</strong>
                <span><?php echo h(date('d/m/Y', strtotime($registro['fecha']))); ?></span>
            </div>
            <div class="info">
                <strong>Marcaciones del día</strong>
                <span><?php echo (int)$registro['cantidad_marcaciones']; ?></span>
            </div>
            <div class="info">
                <strong>Estado actual</strong>
                <span>
                    <?php if ($registro['estado'] === 'OK'): ?>
                        <span class="badge badge-ok">OK</span>
                    <?php elseif ($registro['estado'] === 'OBSERVADO'): ?>
                        <span class="badge badge-obs">OBSERVADO</span>
                    <?php elseif ($registro['estado'] === 'INCOMPLETO'): ?>
                        <span class="badge badge-inc">INCOMPLETO</span>
                    <?php else: ?>
                        <span class="badge badge-err">ERROR</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="return_url" value="<?php echo h($return_url); ?>">
            <div class="form-grid">
                <div>
                    <label for="entrada">Entrada</label>
                    <input
                        type="time"
                        id="entrada"
                        name="entrada"
                        value="<?php echo ($registro['entrada'] ? h(substr($registro['entrada'], 0, 5)) : ''); ?>"
                    >
                </div>

                <div>
                    <label for="salida">Salida</label>
                    <input
                        type="time"
                        id="salida"
                        name="salida"
                        value="<?php echo ($registro['salida'] ? h(substr($registro['salida'], 0, 5)) : ''); ?>"
                    >
                </div>

                <div>
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="OK" <?php echo ($registro['estado'] === 'OK' ? 'selected' : ''); ?>>OK</option>
                        <option value="OBSERVADO" <?php echo ($registro['estado'] === 'OBSERVADO' ? 'selected' : ''); ?>>OBSERVADO</option>
                        <option value="INCOMPLETO" <?php echo ($registro['estado'] === 'INCOMPLETO' ? 'selected' : ''); ?>>INCOMPLETO</option>
                        <option value="ERROR" <?php echo ($registro['estado'] === 'ERROR' ? 'selected' : ''); ?>>ERROR</option>
                    </select>
                </div>

                <div>
                    <label>Total horas calculadas</label>
                    <input
                        type="text"
                        value="<?php echo h($registro['total_horas']); ?>"
                        readonly
                        style="background:#f3f4f6;"
                    >
                </div>

                <div class="full">
                    <label for="observacion">Observación</label>
                    <textarea id="observacion" name="observacion"><?php echo h($registro['observacion']); ?></textarea>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Guardar cambios</button>
                <a href="<?php echo h($return_url); ?>" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>