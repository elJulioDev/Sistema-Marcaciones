<?php
session_start();

$host = 'localhost';
$db   = 'coltauco_RRHH';
$user = 'coltauco';
$pass = 'M.c0lt4uc0.66';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (Exception $e) {
    die("Error BD");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rut = trim($_POST['rut']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE rut = :rut AND activo = 1 LIMIT 1");
    $stmt->execute([':rut' => $rut]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); 

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_rol'] = $user['rol'];

        header("Location: panel.php");
        exit;

    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Login</title>
<style>
body{
    font-family:Arial;
    background:#f4f6f9;
}
.box{
    width:350px;
    margin:100px auto;
    background:#fff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 5px 20px rgba(0,0,0,.1);
}
input{
    width:100%;
    padding:12px;
    margin-bottom:10px;
}
button{
    width:100%;
    padding:12px;
    background:#2563eb;
    color:#fff;
    border:0;
}
.error{
    color:red;
    margin-bottom:10px;
}
</style>
</head>
<body>

<div class="box">
<h2>Ingreso sistema</h2>

<?php if($error): ?>
<div class="error"><?php echo $error; ?></div>
<?php endif; ?>

<form method="post">
    <input type="text" name="rut" placeholder="RUT">
    <input type="password" name="password" placeholder="Contraseña">
    <button>Ingresar</button>
</form>
</div>

</body>
</html>