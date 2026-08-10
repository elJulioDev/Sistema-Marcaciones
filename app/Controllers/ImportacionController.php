<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\ImportadorMarcaciones;
use RuntimeException;
use Throwable;

/**
 * Importación de marcaciones: vista del formulario y endpoint NDJSON que
 * procesa el archivo con barra de progreso en tiempo real.
 */
final class ImportacionController extends Controller
{
    private const EXTENSIONES = ['txt', 'csv'];

    public function index(): string
    {
        return $this->view('importacion/index', [
            'title'     => 'Importar Marcaciones',
            'activeNav' => 'importar',
        ], null);
    }

    /** Endpoint AJAX: POST + streaming NDJSON de eventos. */
    public function importar(): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush(true);

        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');

        $emit = function (array $data): void {
            echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
            @flush();
        };

        if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
            $emit(['phase' => 'error', 'message' => 'No se recibió ningún archivo.']);
            exit;
        }

        $ext = strtolower((string) pathinfo((string) $_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) {
            $emit(['phase' => 'error', 'message' => 'Solo se permiten archivos .txt o .csv.']);
            exit;
        }

        try {
            $importador = new ImportadorMarcaciones($emit);
            $importador->importar(
                (string) $_FILES['archivo']['tmp_name'],
                (string) $_FILES['archivo']['name'],
                (string) ($_POST['periodo'] ?? ''),
                (string) ($_POST['observacion'] ?? ''),
                Auth::id()
            );
        } catch (RuntimeException $e) {
            $emit(['phase' => 'error', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            $emit(['phase' => 'error', 'message' => 'Error interno: ' . $e->getMessage()]);
        }

        exit;
    }
}
