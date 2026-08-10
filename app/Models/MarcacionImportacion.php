<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acceso a datos de marcaciones_importaciones (auditoría de archivos cargados).
 */
final class MarcacionImportacion
{
    /** Crea el registro de auditoría de la importación (totales en 0). */
    public static function crear(string $nombreArchivo, string $periodo, string $observacion, ?int $creadoPor): int
    {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO marcaciones_importaciones
                (nombre_archivo,periodo,observacion,total_lineas,
                 total_insertadas,total_duplicadas,total_invalidas,creado_por)
             VALUES(?,?,?,0,0,0,0,?)"
        );
        $stmt->execute([
            $nombreArchivo,
            ($periodo ?: null),
            ($observacion ?: null),
            $creadoPor,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function actualizarTotales(int $id, int $totalLineas, int $totalInsertadas, int $totalDuplicadas, int $totalInvalidas): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE marcaciones_importaciones
                SET total_lineas=?, total_insertadas=?, total_duplicadas=?, total_invalidas=?
              WHERE id=?'
        );
        $stmt->execute([$totalLineas, $totalInsertadas, $totalDuplicadas, $totalInvalidas, $id]);
    }

    public static function listar(): array
    {
        return Database::pdo()
            ->query('SELECT id, nombre_archivo, periodo, total_lineas, total_insertadas, observacion
                       FROM marcaciones_importaciones ORDER BY id DESC')
            ->fetchAll();
    }

    public static function eliminarPorMes(string $mes): int
    {
        $stmt = Database::pdo()->prepare('DELETE FROM marcaciones_importaciones WHERE periodo = :mes');
        $stmt->execute([':mes' => $mes]);

        return $stmt->rowCount();
    }
}
