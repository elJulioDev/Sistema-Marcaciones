<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Usuario
{
    public int $id;
    public string $rut;
    public string $password;
    public string $nombre;
    public string $rol;
    public bool $activo;

    public static function findByRut(string $rut): ?self
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, rut, password, nombre, rol, activo
               FROM usuarios_sistema
              WHERE rut = :rut AND activo = 1
              LIMIT 1'
        );
        $stmt->execute([':rut' => $rut]);
        $row = $stmt->fetch();

        return $row ? self::fromRow($row) : null;
    }

    private static function fromRow(array $row): self
    {
        $usuario            = new self();
        $usuario->id        = (int) $row['id'];
        $usuario->rut       = (string) $row['rut'];
        $usuario->password  = (string) $row['password'];
        $usuario->nombre    = (string) $row['nombre'];
        $usuario->rol       = (string) $row['rol'];
        $usuario->activo    = (bool) $row['activo'];

        return $usuario;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
}
