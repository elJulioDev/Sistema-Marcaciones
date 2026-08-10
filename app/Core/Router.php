<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router simple: rutas {parámetro}, métodos GET/POST y despacho al
 * controlador (o closure). La ruta se calcula restando BASE_URL del URI.
 *
 * Cada ruta puede exigir autenticación y/o roles específicos:
 *   - null          → pública
 *   - 'login'       → cualquier usuario con sesión iniciada
 *   - 'admin'       → solo rol admin
 *   - ['admin',...] → cualquiera de los roles listados
 */
final class Router
{
    /**
     * @var array<string, array<string, array{handler: callable|array{class-string, string}, auth: string|array|null}>>
     */
    private array $routes = [];
    private string $baseUrl = '';

    public function __construct(?string $baseUrl = null)
    {
        $baseUrl       ??= (string) Env::get('BASE_URL', '');
        $this->baseUrl   = '/' . trim($baseUrl, '/');
    }

    public function get(string $path, callable|array $handler, string|array|null $auth = null): void
    {
        $this->add('GET', $path, $handler, $auth);
    }

    public function post(string $path, callable|array $handler, string|array|null $auth = null): void
    {
        $this->add('POST', $path, $handler, $auth);
    }

    public function add(
        string $method,
        string $path,
        callable|array $handler,
        string|array|null $auth = null
    ): void {
        $this->routes[strtoupper($method)][$path] = [
            'handler' => $handler,
            'auth'    => $auth,
        ];
    }

    public function dispatch(): void
    {
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path   = $this->stripBaseUrl($uri);
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        [$entry, $params] = $this->match($method, $path);

        if ($entry === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo '404 — No encontrado';
            return;
        }

        if (!$this->authorize($entry['auth'])) {
            return;
        }

        $handler = $entry['handler'];

        if (is_array($handler)) {
            [$class, $action] = $handler;
            echo (new $class())->{$action}(...array_values($params));
            return;
        }

        echo $handler(...array_values($params));
    }

    private function authorize(string|array|null $auth): bool
    {
        if ($auth === null) {
            return true;
        }

        if (!Auth::check()) {
            redirect('/login');

            return false;
        }

        $roles = is_array($auth) ? $auth : [$auth];

        if (in_array('login', $roles, true)) {
            return true;
        }

        if (!in_array(Auth::rol(), $roles, true)) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'Acceso denegado'], null);

            return false;
        }

        return true;
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
     * @return array{0: array{handler: callable|array{class-string, string}, auth: string|array|null}|null, 1: array<string, string>}
     */
    private function match(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $route => $entry) {
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

            return [$entry, $params];
        }

        return [null, []];
    }
}
