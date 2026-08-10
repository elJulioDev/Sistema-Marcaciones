<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acceso a datos de marcaciones_importaciones (auditoría de archivos cargados).
 */
final class MarcacionImportacion
{
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
