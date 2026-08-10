<?php

declare(strict_types=1);

use App\Core\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();
require CONFIG_PATH . '/routes.php';

$router->dispatch();
