<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Marcacion;
use App\Models\MarcacionImportacion;
use App\Models\MarcacionResumen;

final class MesController extends Controller
{
    public function index(): string
    {
        $mensaje = '';
        $error   = '';

        if ($this->isPost()) {
            $mes         = trim((string) ($_POST['mes'] ?? ''));
            $confirmacion = isset($_POST['confirmacion']);

            if ($mes === '') {
                $error = 'Debes seleccionar un mes.';
            } elseif (!$confirmacion) {
                $error = 'Debes marcar la casilla de confirmación para eliminar los datos.';
            } elseif (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
                $error = 'El mes seleccionado no es válido.';
            } else {
                $fechaInicio = $mes . '-01';
                $fechaFin    = date('Y-m-t', strtotime($fechaInicio));

                $pdo = Database::pdo();

                try {
                    $pdo->beginTransaction();

                    $eliminadosResumen    = MarcacionResumen::eliminarPorMes($fechaInicio, $fechaFin);
                    $eliminadosBrutos     = Marcacion::eliminarPorMes($fechaInicio, $fechaFin);

                    $pdo->exec('SET SESSION foreign_key_checks=0');
                    $eliminadosImportaciones = MarcacionImportacion::eliminarPorMes($mes);
                    $pdo->exec('SET SESSION foreign_key_checks=1');

                    $pdo->commit();

                    if ($eliminadosResumen === 0 && $eliminadosBrutos === 0 && $eliminadosImportaciones === 0) {
                        $mensaje = 'No se encontraron registros para el mes de ' . $mes . '.';
                    } else {
                        $mensaje = "¡Éxito! Se eliminaron $eliminadosBrutos marcaciones brutas, $eliminadosResumen registros de resumen y $eliminadosImportaciones registros de importación para el mes $mes.";
                    }
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Ocurrió un error al intentar eliminar los registros: ' . $e->getMessage();
                }
            }
        }

        $errorLista = '';
        $importaciones = [];

        try {
            $importaciones = MarcacionImportacion::listar();
        } catch (\Throwable $e) {
            $errorLista = $e->getMessage();
        }

        return $this->view('mes/eliminar', [
            'title'         => 'Eliminar Marcaciones por Mes',
            'activeNav'     => 'eliminar-mes',
            'styles'        => ['/assets/css/mes.css'],
            'mensaje'       => $mensaje,
            'error'         => $error,
            'importaciones' => $importaciones,
            'errorLista'    => $errorLista,
        ]);
    }
}
