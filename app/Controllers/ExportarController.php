<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ExportadorHorasMes;
use App\Services\ExportadorInasistencias;
use Throwable;

/**
 * Descargas XLSX: inasistencias (semana/mes) y horas trabajadas del mes.
 * Los endpoints escriben el binario directamente y terminan la petición.
 */
final class ExportarController extends Controller
{
    public function inasistencias(): never
    {
        $exportador = new ExportadorInasistencias();

        try {
            $resultado = $exportador->generar(
                (string) ($_GET['rango'] ?? 'semana'),
                (string) ($_GET['mes'] ?? ''),
                (string) ($_GET['fecha'] ?? ''),
            );
            self::enviar($resultado);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public function horasMes(): never
    {
        $exportador = new ExportadorHorasMes();

        try {
            $resultado = $exportador->generar(
                (string) ($_GET['mes'] ?? ''),
                (string) ($_GET['dpto'] ?? ''),
                (string) ($_GET['q'] ?? ''),
            );
            self::enviar($resultado);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    /**
     * @param array{data:string, nombre:string, tipo:string} $resultado
     */
    private static function enviar(array $resultado): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $resultado['tipo']);
        header('Content-Disposition: attachment; filename="' . $resultado['nombre'] . '"');
        header('Content-Length: ' . strlen($resultado['data']));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $resultado['data'];
        exit;
    }

    private static function error(Throwable $e): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Error al generar el reporte: ' . $e->getMessage();
        exit;
    }
}
