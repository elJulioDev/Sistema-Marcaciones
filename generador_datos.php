<?php
// generador_datos_masivo.php
date_default_timezone_set('America/Santiago');

// Lista base de 50 empleados ficticios
$empleados_base = [
    ['Informatica', 'Juan Perez', '17520205-0'], ['Informatica', 'Maria Gonzalez', '18123456-7'],
    ['Informatica', 'Pedro Cardenas', '15222333-4'], ['Informatica', 'Camila Orellana', '19444555-6'],
    ['RRHH', 'Pedro Silva', '15987654-3'], ['RRHH', 'Ana Rojas', '19876543-2'],
    ['RRHH', 'Felipe Soto', '14111222-3'], ['RRHH', 'Javiera Mena', '16333444-5'],
    ['Finanzas', 'Carlos Muñoz', '12345678-9'], ['Finanzas', 'Laura Torres', '16789012-K'],
    ['Finanzas', 'Andres Bello', '11222333-4'], ['Finanzas', 'Valeria Cifuentes', '18999000-1'],
    ['Operaciones', 'Diego Soto', '14567890-1'], ['Operaciones', 'Camila Castro', '20123456-8'],
    ['Operaciones', 'Jorge Morales', '11223344-5'], ['Operaciones', 'Luis Riquelme', '13444555-6'],
    ['Operaciones', 'Teresa Alvarado', '15666777-8'],
    ['Salud', 'Valeria Pinto', '19998877-6'], ['Salud', 'Matias Vega', '18887766-5'],
    ['Salud', 'Roberto Gomez', '10111222-3'], ['Salud', 'Sofia Vergara', '17222333-4'],
    ['Salud', 'Emilio Lillo', '14555888-9'],
    ['Educacion', 'Sofia Herrera', '17776655-4'], ['Educacion', 'Luis Castillo', '16665544-3'],
    ['Educacion', 'Carmen Lazo', '12333111-2'], ['Educacion', 'Hector Parra', '15888999-0'],
    ['Educacion', 'Daniela Arriagada', '19444111-5'],
    ['Alcaldia', 'Carmen Gloria', '10555444-2'], ['Alcaldia', 'Roberto Diaz', '13444333-1'],
    ['Alcaldia', 'Alejandra Pizarro', '16777888-9'],
    ['Seguridad', 'Felipe Tapia', '15111222-3'], ['Seguridad', 'Daniela Ruiz', '19222333-4'],
    ['Seguridad', 'Hugo Bravo', '11444555-6'], ['Seguridad', 'Mario Casas', '13666777-8'],
    ['Seguridad', 'Victor Salas', '17888999-0'],
    ['Aseo', 'Hugo Paredes', '12333444-5'], ['Aseo', 'Teresa Salinas', '14555666-7'],
    ['Aseo', 'Carlos Pinto', '10999888-7'], ['Aseo', 'Marta Nuñez', '18111999-K'],
    ['Transito', 'Andres Medina', '16888999-0'], ['Transito', 'Paula Flores', '21000111-K'],
    ['Transito', 'Rodrigo Valdes', '15333222-1'], ['Transito', 'Lorena Caceres', '19666555-4'],
    ['DIDECO', 'Ricardo Guzman', '11777888-9'], ['DIDECO', 'Natalia Riquelme', '18555999-2'],
    ['DIDECO', 'Eduardo Fuentes', '14222333-4'], ['DIDECO', 'Macarena Perez', '16444555-6'],
    ['Obras', 'Esteban Pavez', '13222111-5'], ['Obras', 'Carolina Rios', '17444888-6'],
    ['Obras', 'Sebastian Lira', '19111222-3']
];

// Multiplicar empleados para llegar a 100 y asegurar el volumen de +3000 registros
$empleados = $empleados_base;
foreach ($empleados_base as $emp) {
    // Agregamos un "2" al final del nombre y modificamos ligeramente el RUT
    $rut_parts = explode('-', $emp[2]);
    $nuevo_rut = (intval($rut_parts[0]) + 1000000) . '-' . $rut_parts[1]; 
    $empleados[] = [$emp[0], $emp[1] . ' Dos', $nuevo_rut];
}

$anio = 2026;

// Meses desde enero a abril
$meses_a_generar = [
    1 => 'enero',
    2 => 'febrero',
    3 => 'marzo',
    4 => 'abril'
];

echo "<h1>Generador Masivo de Marcaciones (Enero a Abril 2026)</h1>";

foreach ($meses_a_generar as $mes_num => $mes_nombre) {
    $archivo_salida = "marcaciones_{$mes_nombre}_{$anio}.txt";
    $fp = fopen($archivo_salida, 'w');
    
    // Cabecera
    fwrite($fp, "Departamento\tNombre\tNumero\tFecha/Hora\n");

    $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes_num, $anio);
    $total_registros = 0;

    for ($dia = 1; $dia <= $diasEnMes; $dia++) {
        $fecha = sprintf('%04d-%02d-%02d', $anio, $mes_num, $dia);
        $dia_semana = date('N', strtotime($fecha));

        // Solo Lunes a Viernes
        if ($dia_semana <= 5) {
            foreach ($empleados as $emp) {
                $dpto = $emp[0];
                $nombre = $emp[1];
                $rut = $emp[2];

                // Bajamos la probabilidad de inasistencia de 65% a solo un 15% 
                // para asegurar que existan muchas marcaciones.
                if (rand(1, 100) <= 15) {
                    continue; 
                }

                // Generar Entrada: 07:45 a 08:35
                $hora_entrada = sprintf('%02d:%02d', rand(7, 8), rand(0, 59));
                if (substr($hora_entrada, 0, 2) == '07' && (int)substr($hora_entrada, 3, 2) < 45) {
                    $hora_entrada = '07:45';
                } elseif (substr($hora_entrada, 0, 2) == '08' && (int)substr($hora_entrada, 3, 2) > 35) {
                    $hora_entrada = '08:15';
                }
                
                $fecha_hora_entrada = sprintf('%02d/%02d/%04d %s', $dia, $mes_num, $anio, $hora_entrada);
                fwrite($fp, "$dpto\t$nombre\t$rut\t$fecha_hora_entrada\n");
                $total_registros++;

                // Generar Salida: 17:30 a 18:45
                $hora_salida = sprintf('%02d:%02d', rand(17, 18), rand(0, 59));
                if (substr($hora_salida, 0, 2) == '17' && (int)substr($hora_salida, 3, 2) < 30) {
                    $hora_salida = '17:30';
                }
                $fecha_hora_salida = sprintf('%02d/%02d/%04d %s', $dia, $mes_num, $anio, $hora_salida);

                // 5% de probabilidad de olvidar marcar la salida
                if (rand(1, 100) > 5) {
                    fwrite($fp, "$dpto\t$nombre\t$rut\t$fecha_hora_salida\n");
                    $total_registros++;
                }
            }
        }
    }

    fclose($fp);
    echo "<p>✅ Archivo <b>$archivo_salida</b> generado exitosamente con <b>$total_registros</b> registros.</p>";
}

echo "<hr><p>¡Archivos listos para ser subidos desde tu sistema de importación!</p>";
?>