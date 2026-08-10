<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MarcacionImportacion;
use RuntimeException;

/**
 * Importación de marcaciones desde un archivo TXT/CSV exportado por el reloj
 * de control (columnas separadas por tabulaciones). Emite eventos NDJSON al
 * cliente vía una función emisora para la barra de progreso en tiempo real.
 *
 * Flujo: parseo TSV → dedup por hash md5 → INSERT IGNORE en lotes de 500 →
 * recálculo parcial de resúmenes (respeta editado_manual = 1).
 */
final class ImportadorMarcaciones
{
    private const BATCH_INSERT = 500;
    private const LOTE_DEDUP   = 400;
    private const CHUNK_PARES  = 200;
    private const LOTE_UPSERT  = 100;

    /** @var callable(array): void */
    private $emit;

    private \PDO $pdo;

    private int $totalBruto = 0;
    private int $invalidos  = 0;

    /**
     * @param callable(array): void $emit emisor de eventos NDJSON
     */
    public function __construct(callable $emit)
    {
        $this->emit = $emit;
        $this->pdo  = Database::pdo();
    }

    /**
     * Procesa el archivo completo. Lanza RuntimeException para errores
     * esperados (archivo vacío, sin filas válidas) y otros Throwable
     * para errores internos.
     *
     * @return array{leidos:int, validos:int, insertados:int, duplicados:int, invalidos:int, resumen:int, tiempo:float}
     */
    public function importar(string $rutaTmp, string $nombreArchivo, string $periodo = '', string $observacion = '', ?int $creadoPor = null): array
    {
        $t0 = microtime(true);
        $this->totalBruto = 0;
        $this->invalidos  = 0;

        $this->emit(['phase' => 'parsing', 'progress' => 5, 'message' => 'Leyendo archivo...']);

        $lineas = file($rutaTmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lineas || count($lineas) <= 1) {
            throw new RuntimeException('El archivo está vacío o no contiene datos válidos.');
        }

        $this->totalBruto = count($lineas) - 1;
        $this->emit([
            'phase' => 'parsing',
            'progress' => 10,
            'message' => "Analizando {$this->totalBruto} líneas...",
            'total' => $this->totalBruto,
        ]);

        $validos = $this->parsear($lineas);
        $nValidos = count($validos);

        $this->emit([
            'phase' => 'parsed',
            'progress' => 22,
            'validos' => $nValidos,
            'invalidos' => $this->invalidos,
            'message' => "$nValidos válidos · {$this->invalidos} inválidos",
        ]);

        if ($nValidos === 0) {
            throw new RuntimeException("No se encontraron filas válidas ({$this->invalidos} líneas descartadas).");
        }

        $this->emit(['phase' => 'dedup', 'progress' => 28, 'message' => 'Consultando duplicados en base de datos...']);

        $preResult  = $this->prefiltrarDuplicados($validos);
        $nuevos     = $preResult['nuevos'];
        $dupMemoria = $preResult['omitidos'];
        $nNuevos    = count($nuevos);

        $this->emit([
            'phase'   => 'dedup',
            'progress' => 38,
            'predup'  => $dupMemoria,
            'nuevos'  => $nNuevos,
            'message' => $nNuevos > 0
                ? "$nNuevos registros nuevos · $dupMemoria duplicados ya omitidos"
                : 'Todos los registros ya existen en la base de datos',
        ]);

        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            $pdo->exec('SET SESSION foreign_key_checks=0');

            $idImp = MarcacionImportacion::crear(
                $nombreArchivo,
                trim($periodo),
                trim($observacion),
                $creadoPor
            );

            $insertados = 0;
            $dupBD      = 0;
            $afectadas  = [];

            if ($nNuevos > 0) {
                $nBatches = max(1, (int) ceil($nNuevos / self::BATCH_INSERT));

                for ($b = 0; $b < $nBatches; $b++) {
                    $lote = array_slice($nuevos, $b * self::BATCH_INSERT, self::BATCH_INSERT);
                    $ins  = $this->insertarLote($lote, $idImp);
                    $insertados += $ins;
                    $dupBD      += count($lote) - $ins;

                    foreach ($lote as $row) {
                        $k = $row['rut_base'] . '|' . $row['fecha'];
                        $afectadas[$k] = ['rut_base' => $row['rut_base'], 'fecha' => $row['fecha']];
                    }

                    usleep(200000); // Micropausa: no saturar el servidor por bloque

                    $elapsed = microtime(true) - $t0;
                    $rps     = $elapsed > 0.01 ? (int) (($b + 1) * self::BATCH_INSERT / $elapsed) : 0;
                    $prog    = 38 + (int) round((($b + 1) / $nBatches) * 42);
                    $totalDupHastaAhora = $dupBD + $dupMemoria;

                    $this->emit([
                        'phase'        => 'inserting',
                        'progress'     => $prog,
                        'batch'        => $b + 1,
                        'totalBatches' => $nBatches,
                        'inserted'     => $insertados,
                        'duplicated'   => $totalDupHastaAhora,
                        'rps'          => $rps,
                        'message'      => 'Lote ' . ($b + 1) . "/$nBatches — $insertados insertados",
                    ]);
                }
            } else {
                $this->emit([
                    'phase'        => 'inserting',
                    'progress'     => 80,
                    'batch'        => 0,
                    'totalBatches' => 0,
                    'inserted'     => 0,
                    'duplicated'   => $dupMemoria,
                    'rps'          => 0,
                    'message'      => 'Sin registros nuevos que insertar.',
                ]);
            }

            $totalDup = $dupBD + $dupMemoria;
            $nPares   = count($afectadas);

            $this->emit([
                'phase'   => 'resumen',
                'progress' => 82,
                'pairs'   => $nPares,
                'message' => "Recalculando $nPares resúmenes de asistencia...",
            ]);

            $recalc = $nPares > 0 ? $this->recalcularParcial(array_values($afectadas)) : 0;

            $pdo->exec('SET SESSION foreign_key_checks=1');
            MarcacionImportacion::actualizarTotales($idImp, $this->totalBruto, $insertados, $totalDup, $this->invalidos);

            $pdo->commit();

            $result = [
                'leidos'     => $this->totalBruto,
                'validos'    => $nValidos,
                'insertados' => $insertados,
                'duplicados' => $totalDup,
                'invalidos'  => $this->invalidos,
                'resumen'    => $recalc,
                'tiempo'     => round(microtime(true) - $t0, 2),
            ];

            $this->emit(['phase' => 'done', 'progress' => 100, 'result' => $result]);

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('SET SESSION foreign_key_checks=1');
            throw $e;
        }
    }

    /* ─── Parseo ──────────────────────────────────────────────── */

    /** @return list<array{dpto:string, nombre:string, numero:string, fecha_hora:string}>|false */
    private static function parsearLinea(string $linea): array|false
    {
        $linea = trim($linea);
        if ($linea === '') {
            return false;
        }
        $p = preg_split('/\t+/', $linea);
        if (!is_array($p) || count($p) < 4) {
            return false;
        }

        return [
            'dpto'       => trim($p[0]),
            'nombre'     => trim($p[1]),
            'numero'     => trim($p[2]),
            'fecha_hora' => trim($p[3]),
        ];
    }

    private static function limpiarNumero(mixed $v): string
    {
        return preg_replace('/[^0-9K]/', '', strtoupper(trim((string) $v)));
    }

    /** $n viene sin guiones ("123456789" o "12345678K"); solo quitar el DV. */
    private static function obtenerRutBase(string $n): string
    {
        return strlen($n) > 1 ? substr($n, 0, -1) : $n;
    }

    private static function minsToTime(int $m): string
    {
        return sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60);
    }

    /**
     * Convierte las líneas del archivo en registros válidos con hash md5.
     *
     * @param list<string> $lineas
     * @return list<array{dpto:string, nombre:string, numero:string, rut_base:string, fecha_hora:string, fecha:string, hora:string, hash:string}>
     */
    private function parsear(array $lineas): array
    {
        $validos  = [];
        $invalidos = 0;
        $primera  = true;

        foreach ($lineas as $linea) {
            if ($primera) {
                $primera = false;
                continue;
            }

            $f = self::parsearLinea($linea);
            if (!$f) {
                $invalidos++;
                continue;
            }

            $dpto   = $f['dpto'];
            $nombre = $f['nombre'];
            $numero = self::limpiarNumero($f['numero']);
            $rut    = self::obtenerRutBase($numero);

            if (!$dpto || !$nombre || !$numero || !$rut || !$f['fecha_hora']) {
                $invalidos++;
                continue;
            }

            $dt = \DateTime::createFromFormat('d/m/Y H:i', $f['fecha_hora']);
            if (!$dt) {
                $invalidos++;
                continue;
            }

            $fh    = $dt->format('Y-m-d H:i:s');
            $fecha = $dt->format('Y-m-d');
            $hora  = $dt->format('H:i:s');
            $hash  = md5(strtoupper($dpto) . '|' . strtoupper($nombre) . '|' . $numero . '|' . $fh);

            $validos[] = [
                'dpto'       => $dpto,
                'nombre'     => $nombre,
                'numero'     => $numero,
                'rut_base'   => $rut,
                'fecha_hora' => $fh,
                'fecha'      => $fecha,
                'hora'       => $hora,
                'hash'       => $hash,
            ];
        }

        $this->invalidos = $invalidos;

        return $validos;
    }

    /* ─── Dedup y carga ───────────────────────────────────────── */

    /**
     * Descarta en memoria los registros cuyo hash ya existe en BD.
     *
     * @return array{nuevos: list<array>, omitidos: int}
     */
    private function prefiltrarDuplicados(array $validos): array
    {
        if (empty($validos)) {
            return ['nuevos' => [], 'omitidos' => 0];
        }

        $allHashes = array_map(static fn (array $r): string => $r['hash'], $validos);
        $existentes = [];

        foreach (array_chunk($allHashes, self::LOTE_DEDUP) as $chunk) {
            $ph   = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->pdo->prepare("SELECT hash_registro FROM marcaciones WHERE hash_registro IN ($ph)");
            $stmt->execute($chunk);
            while ($h = $stmt->fetchColumn()) {
                $existentes[$h] = true;
            }
        }

        $nuevos = [];
        foreach ($validos as $row) {
            if (!isset($existentes[$row['hash']])) {
                $nuevos[] = $row;
            }
        }

        return ['nuevos' => $nuevos, 'omitidos' => count($validos) - count($nuevos)];
    }

    /** @param list<array> $lote @return int filas realmente insertadas */
    private function insertarLote(array $lote, int $idImp): int
    {
        if (empty($lote)) {
            return 0;
        }

        $ph   = implode(',', array_fill(0, count($lote), '(?,?,?,?,?,?,?,?,?)'));
        $sql  = "INSERT IGNORE INTO marcaciones
                (id_importacion,dpto,nombre,numero,rut_base,fecha_hora,fecha,hora,hash_registro)
                VALUES $ph";
        $params = [];
        foreach ($lote as $r) {
            array_push(
                $params,
                $idImp,
                $r['dpto'],
                $r['nombre'],
                $r['numero'],
                $r['rut_base'],
                $r['fecha_hora'],
                $r['fecha'],
                $r['hora'],
                $r['hash']
            );
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /* ─── Recálculo parcial de resúmenes ─────────────────────── */

    /**
     * Recalcula los resúmenes de los pares (rut_base, fecha) afectados.
     * El UPSERT respeta editado_manual = 1 (no pisa ediciones manuales).
     *
     * @param list<array{rut_base:string, fecha:string}> $pares
     */
    private function recalcularParcial(array $pares): int
    {
        if (empty($pares)) {
            return 0;
        }

        $allHoras = [];
        $empMap   = [];

        foreach (array_chunk($pares, self::CHUNK_PARES) as $chunk) {
            $inPairs    = implode(',', array_fill(0, count($chunk), '(?,?)'));
            $flatParams = [];
            foreach ($chunk as $par) {
                $flatParams[] = $par['rut_base'];
                $flatParams[] = $par['fecha'];
            }

            $s = $this->pdo->prepare(
                'SELECT rut_base, fecha, hora
                   FROM marcaciones
                  WHERE (rut_base, fecha) IN (' . $inPairs . ')
                  ORDER BY rut_base, fecha, hora ASC, id ASC'
            );
            $s->execute($flatParams);
            while ($row = $s->fetch(\PDO::FETCH_ASSOC)) {
                $allHoras[$row['rut_base'] . '|' . $row['fecha']][] = $row['hora'];
            }

            $s = $this->pdo->prepare(
                'SELECT rut_base, fecha, numero, nombre, dpto
                   FROM marcaciones
                  WHERE (rut_base, fecha) IN (' . $inPairs . ')
                  GROUP BY rut_base, fecha'
            );
            $s->execute($flatParams);
            while ($emp = $s->fetch(\PDO::FETCH_ASSOC)) {
                $empMap[$emp['rut_base'] . '|' . $emp['fecha']] = $emp;
            }
        }

        $resumenRows = [];
        foreach ($pares as $par) {
            $key = $par['rut_base'] . '|' . $par['fecha'];
            if (!isset($empMap[$key])) {
                continue;
            }

            $emp    = $empMap[$key];
            $marcas = $allHoras[$key] ?? [];
            $n      = count($marcas);

            $entrada = $salida = $totalH = null;
            $estado  = 'OK';
            $obs     = '';

            if ($n > 0) {
                $entrada = $marcas[0];
            }

            if ($n === 1) {
                $estado = 'INCOMPLETO';
                $obs    = 'Solo existe una marcación en el día.';
            } elseif ($n >= 2) {
                $salida = $marcas[$n - 1];
                $dif    = (int) ((strtotime($par['fecha'] . ' ' . $salida)
                    - strtotime($par['fecha'] . ' ' . $entrada)) / 60);
                if ($dif < 0) {
                    $estado = 'ERROR';
                    $obs    = 'La salida calculada es anterior a la entrada.';
                } else {
                    $totalH = self::minsToTime($dif);
                    if ($n > 2) {
                        $estado = 'OBSERVADO';
                        $obs    = "Día con $n marcaciones. Revisar detalle.";
                    }
                }
            }

            $resumenRows[] = [
                $par['rut_base'],
                $emp['numero'],
                $emp['nombre'],
                $emp['dpto'],
                $par['fecha'],
                $entrada,
                $salida,
                $totalH,
                $n,
                $estado,
                $obs,
            ];
        }

        if (empty($resumenRows)) {
            return 0;
        }

        $upsertTpl = "INSERT INTO marcaciones_resumen
            (rut_base,numero,nombre,dpto,fecha,entrada,salida,total_horas,
             cantidad_marcaciones,estado,observacion,editado_manual)
            VALUES %s
            ON DUPLICATE KEY UPDATE
                numero               = VALUES(numero),
                nombre               = VALUES(nombre),
                dpto                 = VALUES(dpto),
                cantidad_marcaciones = VALUES(cantidad_marcaciones),
                entrada     = IF(editado_manual=1, entrada,     VALUES(entrada)),
                salida      = IF(editado_manual=1, salida,      VALUES(salida)),
                total_horas = IF(editado_manual=1, total_horas, VALUES(total_horas)),
                estado      = IF(editado_manual=1, estado,      VALUES(estado)),
                observacion = IF(editado_manual=1, observacion, VALUES(observacion))";

        foreach (array_chunk($resumenRows, self::LOTE_UPSERT) as $chunk) {
            $ph     = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?,0)'));
            $params = [];
            foreach ($chunk as $row) {
                foreach ($row as $val) {
                    $params[] = $val;
                }
            }
            $this->pdo->prepare(sprintf($upsertTpl, $ph))->execute($params);
            usleep(100000); // Micropausa: no saturar el servidor por bloque
        }

        return count($resumenRows);
    }

    /** @param array $data */
    private function emit(array $data): void
    {
        ($this->emit)($data);
    }
}
