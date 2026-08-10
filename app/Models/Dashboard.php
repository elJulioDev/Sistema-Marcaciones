<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Consultas agregadas del panel de control (dashboard administrativo).
 * Todo se calcula sobre un período [inicio, fin) en `marcaciones_resumen`
 * y `marcaciones`, sin dependencias de otros módulos.
 */
final class Dashboard
{
    /**
     * KPIs del período: empleados, marcas brutas, registros del resumen,
     * estados (OK/OBSERVADO/INCOMPLETO/ERROR) y total de horas (segundos).
     */
    public static function kpis(string $inicio, string $fin): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(DISTINCT mr.rut_base)                                  AS empleados,
                    COUNT(DISTINCT mr.fecha)                                     AS dias_datos,
                    COUNT(*)                                                     AS registros,
                    COALESCE(SUM(CASE WHEN mr.estado='OK' THEN 1 ELSE 0 END),0)  AS ok,
                    COALESCE(SUM(CASE WHEN mr.estado='OBSERVADO' THEN 1 ELSE 0 END),0) AS obs,
                    COALESCE(SUM(CASE WHEN mr.estado='INCOMPLETO' THEN 1 ELSE 0 END),0) AS inc,
                    COALESCE(SUM(CASE WHEN mr.estado='ERROR' THEN 1 ELSE 0 END),0)      AS err,
                    COALESCE(SUM(TIME_TO_SEC(COALESCE(mr.total_horas,'00:00:00'))),0)   AS horas_seg
               FROM marcaciones_resumen mr
              WHERE mr.fecha >= :ini AND mr.fecha < :fin"
        );
        $stmt->execute([':ini' => $inicio, ':fin' => $fin]);
        $row = $stmt->fetch() ?: [];

        $stmtMarcas = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM marcaciones WHERE fecha >= :ini AND fecha < :fin'
        );
        $stmtMarcas->execute([':ini' => $inicio, ':fin' => $fin]);

        return [
            'empleados' => (int) ($row['empleados'] ?? 0),
            'dias_datos' => (int) ($row['dias_datos'] ?? 0),
            'registros'  => (int) ($row['registros'] ?? 0),
            'marcas'     => (int) $stmtMarcas->fetchColumn(),
            'ok'         => (int) ($row['ok'] ?? 0),
            'obs'        => (int) ($row['obs'] ?? 0),
            'inc'        => (int) ($row['inc'] ?? 0),
            'err'        => (int) ($row['err'] ?? 0),
            'horas_seg'  => (int) ($row['horas_seg'] ?? 0),
        ];
    }

    /**
     * Marcas brutas por día del período, indexadas por fecha (YYYY-MM-DD).
     */
    public static function marcasPorDia(string $inicio, string $fin): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT fecha, COUNT(*) AS total
               FROM marcaciones
              WHERE fecha >= :ini AND fecha < :fin
              GROUP BY fecha ORDER BY fecha'
        );
        $stmt->execute([':ini' => $inicio, ':fin' => $fin]);

        $porDia = [];
        foreach ($stmt->fetchAll() as $r) {
            $porDia[$r['fecha']] = (int) $r['total'];
        }

        return $porDia;
    }

    /**
     * Agregación por departamento: empleados, registros, OK y horas (segundos).
     */
    public static function porDepartamento(string $inicio, string $fin): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT mr.dpto,
                    COUNT(DISTINCT mr.rut_base) AS empleados,
                    COUNT(*)                     AS registros,
                    COALESCE(SUM(CASE WHEN mr.estado='OK' THEN 1 ELSE 0 END),0) AS ok,
                    COALESCE(SUM(TIME_TO_SEC(COALESCE(mr.total_horas,'00:00:00'))),0) AS horas_seg
               FROM marcaciones_resumen mr
              WHERE mr.fecha >= :ini AND mr.fecha < :fin
                AND mr.dpto != ''
              GROUP BY mr.dpto
              ORDER BY empleados DESC, mr.dpto ASC"
        );
        $stmt->execute([':ini' => $inicio, ':fin' => $fin]);

        return $stmt->fetchAll();
    }

    /**
     * Últimas incidencias (registros no OK), más recientes primero.
     */
    public static function ultimasIncidencias(int $limit = 8): array
    {
        $stmt = Database::pdo()->query(
            "SELECT id, rut_base, nombre, dpto, fecha, estado, observacion
               FROM marcaciones_resumen
              WHERE estado IN ('OBSERVADO','INCOMPLETO','ERROR')
              ORDER BY fecha DESC, id DESC
              LIMIT $limit"
        );

        return $stmt->fetchAll();
    }

    /** Últimas importaciones registradas. */
    public static function ultimasImportaciones(int $limit = 6): array
    {
        $stmt = Database::pdo()->query(
            'SELECT id, nombre_archivo, periodo, total_lineas, total_insertadas,
                    total_duplicadas, total_invalidas, created_at
               FROM marcaciones_importaciones
              ORDER BY id DESC
              LIMIT ' . $limit
        );

        return $stmt->fetchAll();
    }
}
