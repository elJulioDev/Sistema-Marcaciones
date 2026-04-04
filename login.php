<?php
session_start();
require_once __DIR__ . '/inc/db.php';

// Si el usuario ya tiene sesión iniciada, redirigir al panel principal
if (isset($_SESSION['usuario_id'])) {
    header("Location: panel.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut']);
    $password = trim($_POST['clave']); // Usamos 'clave' del input para validar el password

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE rut = :rut AND activo = 1 LIMIT 1");
        $stmt->execute([':rut' => $rut]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC); 

        if ($user && password_verify($password, $user['password'])) {
            // Asignar variables de sesión del sistema de marcaciones
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            $_SESSION['usuario_rol'] = $user['rol'];

            header("Location: panel.php");
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } catch (Exception $e) {
        $error = "Error al conectar con la base de datos.";
    }
}

$year = date('Y');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión | Sistema de Marcaciones</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%232563eb'><path fill-rule='evenodd' d='M6.75 2.25A.75.75 0 017.5 3v1.5h9V3A.75.75 0 0118 3v1.5h.75a3 3 0 013 3v11.25a3 3 0 01-3 3H5.25a3 3 0 01-3-3V7.5a3 3 0 013-3H6V3a.75.75 0 01.75-.75zm13.5 9a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5v7.5a1.5 1.5 0 001.5 1.5h13.5a1.5 1.5 0 001.5-1.5v-7.5z' clip-rule='evenodd' /></svg>">
    <link rel="stylesheet" href="static/css/login.css">
</head>
<body>

<div class="login-container">

    <div class="institution-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Municipalidad de Coltauco
    </div>

    <div class="login-card">

        <div class="card-header">
            <h1>Sistema de Marcaciones</h1>
            <p>Acceso para gestión de Recursos Humanos</p>
        </div>

        <div class="card-body">

            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="form-row">
                    <div class="form-group">
                        <label for="rut">RUT Funcionario</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </span>
                            <input id="rut" class="form-control" type="text" name="rut"
                                   placeholder="12345678-9" required
                                   value="<?php echo $error ? htmlspecialchars(isset($_POST['rut']) ? $_POST['rut'] : '') : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="clave">Contraseña</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </span>
                            <input id="clave" class="form-control" type="password" name="clave"
                                   placeholder="••••••••" required>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn-submit">
                    Ingresar al Sistema
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path>
                        <path d="m12 5 7 7-7 7"></path>
                    </svg>
                </button>
            </form>
        </div>

        <div class="card-footer">
            &copy; <?php echo $year; ?> <strong>Depto. de Informática</strong> — Municipalidad de Coltauco
        </div>

    </div>
</div>

</body>
</html>