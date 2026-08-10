<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestión de la sesión de usuario y control de roles (admin/operador).
 * Mantiene las mismas claves de sesión que el legado
 * (usuario_id, usuario_nombre, usuario_rol) para coexistir con él.
 */
final class Auth
{
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_OPERADOR = 'operador';

    public static function login(int $id, string $nombre, string $rol): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario_id']     = $id;
        $_SESSION['usuario_nombre'] = $nombre;
        $_SESSION['usuario_rol']    = $rol;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['usuario_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    public static function nombre(): ?string
    {
        return $_SESSION['usuario_nombre'] ?? null;
    }

    public static function rol(): ?string
    {
        return $_SESSION['usuario_rol'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::rol() === self::ROLE_ADMIN;
    }
}
