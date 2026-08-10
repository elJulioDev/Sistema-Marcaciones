<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cargador de .env. Lee el archivo, almacena los valores en memoria y los
 * expone también vía $_ENV y putenv() para compatibilidad con getenv().
 * Las variables ya definidas en el entorno real no se pisan.
 */
final class Env
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key   = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $existing = getenv($key);
            self::$values[$key] = ($existing !== false && $existing !== '') ? $existing : $value;

            $_ENV[$key]   = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }
}
