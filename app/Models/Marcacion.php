<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acceso a datos de marcaciones (registros brutos del reloj de control).
 */
final class Marcacion
{
    /** Marcas de un empleado en una fecha (detalle para consulta/observaciones). */
    public static function detalle(string $rutBase, string $fecha): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT hora
               FROM marcaciones
              WHERE rut_base = :rut_base AND fecha = :fecha
              ORDER BY hora ASC, id ASC'
        );
        $stmt->execute([':rut_base' => $rutBase, ':fecha' => $fecha]);

        return $stmt->fetchAll();
    }

    /** Conteo de marcas de un empleado en una fecha (usado por el calendario). */
    public static function contarDia(string $rutBase, string $fecha): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM marcaciones WHERE rut_base = :rut_base AND fecha = :fecha'
        );
        $stmt->execute([':rut_base' => $rutBase, ':fecha' => $fecha]);

        return (int) $stmt->fetchColumn();
    }

    public static function eliminarPorMes(string $inicio, string $fin): int
    {
        $stmt = Database::pdo()->prepare('DELETE FROM marcaciones WHERE fecha >= :inicio AND fecha <= :fin');
        $stmt->execute([':inicio' => $inicio, ':fin' => $fin]);

        return $stmt->rowCount();
    }
}
