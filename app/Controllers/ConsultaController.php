<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\MarcacionResumen;

final class ConsultaController extends Controller
{
    public function index(): string
    {
        $rut     = trim((string) ($_GET['rut'] ?? ''));
        $periodo = trim((string) ($_GET['periodo'] ?? ''));

        $error       = '';
        $funcionario = null;
        $resumenes   = [];

        if ($rut !== '') {
            if (!validar_rut($rut)) {
                $error = 'El RUT ingresado no es válido.';
            } else {
                $funcionario = MarcacionResumen::datosFuncionario(rut_cuerpo($rut));

                if ($funcionario === null) {
                    $error = 'No se encontraron marcaciones para el RUT consultado.';
                } else {
                    $resumenes = MarcacionResumen::resumenesPorRut(rut_cuerpo($rut), $periodo);
                }
            }
        }

        return $this->view('consulta/index', [
            'title'      => 'Consulta de marcaciones',
            'activeNav'  => 'consulta',
            'rut'        => $rut,
            'periodo'    => $periodo,
            'error'      => $error,
            'funcionario'=> $funcionario,
            'resumenes'  => $resumenes,
        ]);
    }
}
