<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router simple: rutas {parámetro}, métodos GET/POST y despacho al
 * controlador (o closure). La ruta se calcula restando BASE_URL del URI.
 */
final class Router
{
    /**
     * @var array<string, array<string, callable|array{class-string, string}>>
     */
    private array $routes = [];
    private string $baseUrl = '';

    public function __construct(?string $baseUrl = null)
    {
        $baseUrl       ??= (string) Env::get('BASE_URL', '');
        $this->baseUrl   = '/' . trim($baseUrl, '/');
    }

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function dispatch(): void
    {
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path   = $this->stripBaseUrl($uri);
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        [$handler, $params] = $this->match($method, $path);

        if ($handler === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo '404 — No encontrado';
            return;
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;
            $controller = new $class();
            echo $controller->{$action}(...array_values($params));
            return;
        }

        echo $handler(...array_values($params));
    }

    private function stripBaseUrl(string $uri): string
    {
        $path = $uri;
        if ($this->baseUrl !== '/' && str_starts_with($path, $this->baseUrl)) {
            $path = substr($path, strlen($this->baseUrl));
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * @return array{0: callable|array{class-string, string}|null, 1: array<string, string>}
     */
    private function match(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = '#^' . preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $route) . '$#';

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            array_shift($matches);
            preg_match_all('/\{([a-zA-Z_]+)\}/', $route, $names);

            $params = [];
            foreach ($names[1] as $i => $name) {
                $params[$name] = $matches[$i] ?? null;
            }

            return [$handler, $params];
        }

        return [null, []];
    }
}
