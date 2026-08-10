<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controlador base: atajos para renderizar vistas, redirigir, responder JSON
 * y consultar el método HTTP de la petición.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'app'): string
    {
        return View::render($template, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    protected function json(mixed $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
