<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\MarcacionResumen;

final class MarcacionController extends Controller
{
    private const ESTADOS = ['OK', 'OBSERVADO', 'INCOMPLETO', 'ERROR'];

    public function editar(): string
    {
        $id    = (int) ($_GET['id'] ?? 0);
        $rut   = trim((string) ($_GET['rut'] ?? ''));
        $fecha = trim((string) ($_GET['fecha'] ?? ''));

        if ($id <= 0 && ($rut === '' || $fecha === '')) {
            return 'ID o parámetros no válidos.';
        }

        if ($id > 0) {
            $registro = MarcacionResumen::find($id);
        } else {
            $registro = MarcacionResumen::findPorRutFecha(rut_cuerpo($rut), $fecha);

            if ($registro) {
                $id = (int) $registro['id'];
            } else {
                $emp = MarcacionResumen::datosEmpleado(rut_cuerpo($rut));

                if ($emp) {
                    $registro = [
                        'id' => 0,
                        'rut_base' => rut_cuerpo($rut),
                        'nombre' => $emp['nombre'],
                        'dpto' => $emp['dpto'],
                        'numero' => $emp['numero'],
                        'fecha' => $fecha,
                        'entrada' => null,
                        'salida' => null,
                        'total_horas' => null,
                        'cantidad_marcaciones' => 0,
                        'estado' => 'ERROR',
                        'observacion' => '',
                        'editado_manual' => 0,
                    ];
                }
            }
        }

        if ($registro === null) {
            return 'Registro o empleado no encontrado.';
        }

        $mensaje    = '';
        $error      = '';
        $returnUrl  = $this->returnUrl();

        if ($this->isPost()) {
            $entrada     = normalizar_hora((string) ($_POST['entrada'] ?? ''));
            $salida      = normalizar_hora((string) ($_POST['salida'] ?? ''));
            $estado      = trim((string) ($_POST['estado'] ?? 'OK'));
            $observacion = trim((string) ($_POST['observacion'] ?? ''));

            if ($entrada === false) {
                $error = 'La hora de entrada no tiene un formato válido.';
            } elseif ($salida === false) {
                $error = 'La hora de salida no tiene un formato válido.';
            } elseif (!in_array($estado, self::ESTADOS, true)) {
                $error = 'El estado no es válido.';
            } else {
                $totalHoras = null;

                if ($entrada !== null && $salida !== null) {
                    $ts1 = strtotime('2000-01-01 ' . $entrada);
                    $ts2 = strtotime('2000-01-01 ' . $salida);

                    if ($ts2 < $ts1) {
                        $estado = 'ERROR';
                        if ($observacion === '') {
                            $observacion = 'La salida es anterior a la entrada.';
                        }
                    } else {
                        $totalHoras = minutos_a_time((int) (($ts2 - $ts1) / 60));
                    }
                } elseif ($entrada !== null && $salida === null) {
                    if ($estado === 'OK') {
                        $estado = 'INCOMPLETO';
                    }
                } elseif ($entrada === null && $salida !== null) {
                    $estado = 'ERROR';
                    if ($observacion === '') {
                        $observacion = 'Existe salida pero no entrada.';
                    }
                }

                if ($id > 0) {
                    MarcacionResumen::update([
                        'id'          => $id,
                        'entrada'     => $entrada,
                        'salida'      => $salida,
                        'total_horas' => $totalHoras,
                        'estado'      => $estado,
                        'observacion'=> $observacion,
                    ]);
                } else {
                    $id = MarcacionResumen::create([
                        'rut_base'     => $registro['rut_base'],
                        'numero'       => $registro['numero'],
                        'nombre'       => $registro['nombre'],
                        'dpto'         => $registro['dpto'],
                        'fecha'        => $registro['fecha'],
                        'entrada'      => $entrada,
                        'salida'       => $salida,
                        'total_horas'  => $totalHoras,
                        'estado'       => $estado,
                        'observacion'  => $observacion,
                    ]);
                }

                $registro = MarcacionResumen::find($id);
                $mensaje  = 'Marcación resumen actualizada correctamente.';
            }
        }

        return $this->view('marcacion/editar', [
            'title'     => 'Editar marcación resumen',
            'activeNav' => 'observaciones',
            'registro'  => $registro,
            'mensaje'   => $mensaje,
            'error'     => $error,
            'returnUrl' => $returnUrl,
        ]);
    }

    /** URL de retorno (botón Volver), validada para evitar redirecciones abiertas. */
    private function returnUrl(): string
    {
        $url = trim((string) ($_POST['return_url'] ?? ($_GET['return_url'] ?? '')));

        if ($url === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            return base_url('/observaciones');
        }

        return $url;
    }
}
