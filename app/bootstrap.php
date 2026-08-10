<?php

declare(strict_types=1);

/**
 * Bootstrap de la aplicación.
 * Carga .env, autoloader PSR-4 simple (sin Composer), helpers, sesión y
 * configuración global según APP_ENV.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

App\Core\Env::load(ROOT_PATH . '/.env');

date_default_timezone_set(App\Core\Env::get('APP_TIMEZONE', 'America/Santiago'));

if (App\Core\Env::get('APP_ENV', 'production') === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

require_once APP_PATH . '/Support/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
