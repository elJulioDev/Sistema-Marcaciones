<?php
// generador_datos_masivo.php
date_default_timezone_set('America/Santiago');

// Lista ampliada de 50 empleados ficticios para generar mucho más volumen
$empleados = [
    // Informática
    ['Informatica', 'Juan Perez', '17520205-0'], ['Informatica', 'Maria Gonzalez', '18123456-7'],
    ['Informatica', 'Pedro Cardenas', '15222333-4'], ['Informatica', 'Camila Orellana', '19444555-6'],
    // RRHH
    ['RRHH', 'Pedro Silva', '15987654-3'], ['RRHH', 'Ana Rojas', '19876543-2'],
    ['RRHH', 'Felipe Soto', '14111222-3'], ['RRHH', 'Javiera Mena', '16333444-5'],
    // Finanzas
    ['Finanzas', 'Carlos Muñoz', '12345678-9'], ['Finanzas', 'Laura Torres', '16789012-K'],
    ['Finanzas', 'Andres Bello', '11222333-4'], ['Finanzas', 'Valeria Cifuentes', '18999000-1'],
    // Operaciones
    ['Operaciones', 'Diego Soto', '14567890-1'], ['Operaciones', 'Camila Castro', '20123456-8'],
    ['Operaciones', 'Jorge Morales', '11223344-5'], ['Operaciones', 'Luis Riquelme', '13444555-6'],
    ['Operaciones', 'Teresa Alvarado', '15666777-8'],
    // Salud
    ['Salud', 'Valeria Pinto', '19998877-6'], ['Salud', 'Matias Vega', '18887766-5'],
    ['Salud', 'Roberto Gomez', '10111222-3'], ['Salud', 'Sofia Vergara', '17222333-4'],
    ['Salud', 'Emilio Lillo', '14555888-9'],
    // Educacion
    ['Educacion', 'Sofia Herrera', '17776655-4'], ['Educacion', 'Luis Castillo', '16665544-3'],
    ['Educacion', 'Carmen Lazo', '12333111-2'], ['Educacion', 'Hector Parra', '15888999-0'],
    ['Educacion', 'Daniela Arriagada', '19444111-5'],
    // Alcaldia
    ['Alcaldia', 'Carmen Gloria', '10555444-2'], ['Alcaldia', 'Roberto Diaz', '13444333-1'],
    ['Alcaldia', 'Alejandra Pizarro', '16777888-9'],
    // Seguridad
    ['Seguridad', 'Felipe Tapia', '15111222-3'], ['Seguridad', 'Daniela Ruiz', '19222333-4'],
    ['Seguridad', 'Hugo Bravo', '11444555-6'], ['Seguridad', 'Mario Casas', '13666777-8'],
    ['Seguridad', 'Victor Salas', '17888999-0'],
    // Aseo y Ornato
    ['Aseo', 'Hugo Paredes', '12333444-5'], ['Aseo', 'Teresa Salinas', '14555666-7'],
    ['Aseo', 'Carlos Pinto', '10999888-7'], ['Aseo', 'Marta Nuñez', '18111999-K'],
    // Transito
    ['Transito', 'Andres Medina', '16888999-0'], ['Transito', 'Paula Flores', '21000111-K'],
    ['Transito', 'Rodrigo Valdes', '15333222-1'], ['Transito', 'Lorena Caceres', '19666555-4'],
    // DIDECO
    ['DIDECO', 'Ricardo Guzman', '11777888-9'], ['DIDECO', 'Natalia Riquelme', '18555999-2'],
    ['DIDECO', 'Eduardo Fuentes', '14222333-4'], ['DIDECO', 'Macarena Perez', '16444555-6'],
    // Obras
    ['Obras', 'Esteban Pavez', '13222111-5'], ['Obras', 'Carolina Rios', '17444888-6'],
    ['Obras', 'Sebastian Lira', '19111222-3']
];

$anio = 2026;

// 1. Cambiamos el mes para generar únicamente Mayo (5)
$meses_a_generar = [
    5 => 'mayo'
];

echo "<h1>Generador Masivo de Marcaciones (Alta Inasistencia)</h1>";

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

        // Lunes a Viernes
        if ($dia_semana <= 5) {
            foreach ($empleados as $emp) {
                $dpto = $emp[0];
                $nombre = $emp[1];
                $rut = $emp[2];

                // 2. 65% de probabilidad de inasistencia completa en el día (Más faltas que asistencias)
                if (rand(1, 100) <= 65) {
                    continue; 
                }

                // Generar Entrada: 07:45 a 08:35
                $hora_entrada = sprintf('%02d:%02d', rand(7, 8), rand(0, 59));
                if (substr($hora_entrada, 0, 2) == '07' && (int)substr($hora_entrada, 3, 2) < 45) {
                    $hora_entrada = '07:45';
                } elseif (substr($hora_entrada, 0, 2) == '08' && (int)substr($hora_entrada, 3, 2) > 35) {
                    $hora_entrada = '08:15'; // Para no poner entradas tan tarde
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

                // 5% de probabilidad de olvidar marcar la salida (Aumentado levemente para generar más incompletos)
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