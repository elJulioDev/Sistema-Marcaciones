<?php
session_start();

// Vaciar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de la sesión para cerrarla por completo de forma segura (PHP 5.6+)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión en el servidor
session_destroy();

// Redirigir al login
header("Location: login.php");
exit;