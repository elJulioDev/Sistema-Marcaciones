<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acceso a datos de marcaciones_resumen (resumen calculado por rut_base+fecha).
 * Contiene las consultas de los módulos consulta, observaciones, calendario
 * y edición de resumen (antes duplicadas en cada página plana).
 */
final class MarcacionResumen
{
    public const ESTADOS = ['OK', 'OBSERVADO', 'INCOMPLETO', 'ERROR'];

    private const SELECT_LISTADO = <<<'SQL'
        SELECT mr.id, mr.rut_base, mr.numero, mr.nombre, mr.dpto, mr.fecha,
               mr.entrada, mr.salida, mr.total_horas, mr.cantidad_marcaciones,
               mr.estado, mr.observacion, mr.editado_manual, mr.updated_at
          FROM marcaciones_resumen mr
        SQL;

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM marcaciones_resumen WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function findPorRutFecha(string $rutBase, string $fecha): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM marcaciones_resumen WHERE rut_base = :rut AND fecha = :fecha LIMIT 1'
        );
        $stmt->execute([':rut' => $rutBase, ':fecha' => $fecha]);

        return $stmt->fetch() ?: null;
    }

    /** Datos básicos del empleado (último registro con datos). */
    public static function datosEmpleado(string $rutBase): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT nombre, dpto, numero FROM marcaciones_resumen
              WHERE rut_base = :rut ORDER BY fecha DESC LIMIT 1'
        );
        $stmt->execute([':rut' => $rutBase]);

        return $stmt->fetch() ?: null;
    }

    public static function datosFuncionario(string $rutBase): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT nombre, dpto, numero, rut_base FROM marcaciones_resumen
              WHERE rut_base = :rut ORDER BY fecha DESC LIMIT 1'
        );
        $stmt->execute([':rut' => $rutBase]);

        return $stmt->fetch() ?: null;
    }

    public static function update(array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE marcaciones_resumen
                SET entrada = :entrada, salida = :salida, total_horas = :total_horas,
                    estado = :estado, observacion = :observacion, editado_manual = 1
              WHERE id = :id'
        );
        $stmt->execute([
            ':entrada'       => $data['entrada'],
            ':salida'        => $data['salida'],
            ':total_horas'   => $data['total_horas'],
            ':estado'        => $data['estado'],
            ':observacion'   => $data['observacion'],
            ':id'            => (int) $data['id'],
        ]);
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO marcaciones_resumen
                (rut_base, numero, nombre, dpto, fecha, entrada, salida, total_horas,
                 cantidad_marcaciones, estado, observacion, editado_manual)
             VALUES
                (:rut_base, :numero, :nombre, :dpto, :fecha, :entrada, :salida, :total_horas,
                 0, :estado, :observacion, 1)'
        );
        $stmt->execute([
            ':rut_base'     => $data['rut_base'],
            ':numero'       => $data['numero'],
            ':nombre'       => $data['nombre'],
            ':dpto'         => $data['dpto'],
            ':fecha'        => $data['fecha'],
            ':entrada'      => $data['entrada'],
            ':salida'       => $data['salida'],
            ':total_horas'  => $data['total_horas'],
            ':estado'       => $data['estado'],
            ':observacion'  => $data['observacion'],
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function departamentos(): array
    {
        return Database::pdo()
            ->query("SELECT DISTINCT dpto FROM marcaciones_resumen WHERE dpto != '' ORDER BY dpto")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /* ─── Consulta de funcionario ────────────────────────────── */

    /** Resúmenes de un rut_base, con detalle de marcas por día (patrón N+1 como el legado). */
    public static function resumenesPorRut(string $rutBase, string $periodo = ''): array
    {
        $where  = ['mr.rut_base = :rut_base'];
        $params = [':rut_base' => $rutBase];

        if ($periodo !== '') {
            $where[]          = "DATE_FORMAT(mr.fecha, '%Y-%m') = :periodo";
            $params[':periodo'] = $periodo;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT mr.id, mr.fecha, mr.entrada, mr.salida, mr.total_horas,
                    mr.cantidad_marcaciones, mr.estado, mr.observacion,
                    mr.editado_manual, mr.updated_at
               FROM marcaciones_resumen mr
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY mr.fecha DESC'
        );
        $stmt->execute($params);
        $resumenes = $stmt->fetchAll();

        foreach ($resumenes as $k => $r) {
            $resumenes[$k]['detalle_marcaciones'] = Marcacion::detalle($r['rut_base'] ?? $rutBase, $r['fecha']);
        }

        return $resumenes;
    }

    /* ─── Observaciones (listado paginado) ───────────────────── */

    /**
     * @param array{filtro_estado?: string, q?: string, periodo?: string} $filtros
     * @return array{total: int, rows: array}
     */
    public static function listado(array $filtros, int $limit, int $offset): array
    {
        $where  = ["mr.estado IN ('OBSERVADO','INCOMPLETO','ERROR')"];
        $params = [];

        $filtroEstado = $filtros['filtro_estado'] ?? '';
        if ($filtroEstado !== '' && in_array($filtroEstado, self::ESTADOS, true)) {
            $where[array_key_last($where)] = 'mr.estado = :estado';
            $params[':estado'] = $filtroEstado;
        }

        if (($q = $filtros['q'] ?? '') !== '') {
            $where[] = '(mr.nombre LIKE :q1 OR mr.numero LIKE :q2 OR mr.rut_base LIKE :q3
                         OR mr.dpto LIKE :q4 OR mr.observacion LIKE :q5)';
            foreach ([':q1', ':q2', ':q3', ':q4', ':q5'] as $ph) {
                $params[$ph] = "%$q%";
            }
        }

        if (($periodo = $filtros['periodo'] ?? '') !== '') {
            $where[]          = "DATE_FORMAT(mr.fecha, '%Y-%m') = :periodo";
            $params[':periodo'] = $periodo;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $stmtCount = Database::pdo()->prepare('SELECT COUNT(*) FROM marcaciones_resumen mr' . $whereSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $stmt = Database::pdo()->prepare(
            self::SELECT_LISTADO . $whereSql . ' ORDER BY mr.fecha DESC, mr.nombre ASC'
                . ' LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as $k => $r) {
            $rows[$k]['detalle_marcaciones'] = Marcacion::detalle($r['rut_base'], $r['fecha']);
        }

        return ['total' => $total, 'rows' => $rows];
    }

    /* ─── Calendario ─────────────────────────────────────────── */

    /**
     * Puntos de estado por fecha del mes (para las celdas del calendario).
     *
     * @param array{dpto?: string, estado?: string, q?: string} $filtros
     */
    public static function dotsPorMes(string $inicio, string $fin, array $filtros): array
    {
        [$where, $params] = self::filtrosBase($filtros, "fecha >= :m_start AND fecha < :m_end", ':m_start', $inicio, ':m_end', $fin);

        $stmt = Database::pdo()->prepare(
            "SELECT fecha, COUNT(*) AS total,
                    SUM(CASE WHEN estado='OK' THEN 1 ELSE 0 END) AS ok_cnt,
                    SUM(CASE WHEN estado='INCOMPLETO' THEN 1 ELSE 0 END) AS inc_cnt,
                    SUM(CASE WHEN estado='OBSERVADO' THEN 1 ELSE 0 END) AS obs_cnt,
                    SUM(CASE WHEN estado='ERROR' THEN 1 ELSE 0 END) AS err_cnt
               FROM marcaciones_resumen WHERE $where GROUP BY fecha"
        );
        $stmt->execute($params);

        $dots = [];
        foreach ($stmt->fetchAll() as $r) {
            $dots[$r['fecha']] = $r;
        }

        return $dots;
    }

    /**
     * KPIs (empleados, días, ok/inc/obs/err) para una semana o un mes.
     *
     * @param list<string>|null $fechasSemana fechas exactas (modo semana) o null (rango mensual)
     * @param array{dpto?: string, estado?: string, q?: string} $filtros
     */
    public static function stats(?array $fechasSemana, string $inicio, string $fin, array $filtros): array
    {
        if ($fechasSemana !== null) {
            $ph    = implode(',', array_fill(0, count($fechasSemana), '?'));
            $where = "fecha IN ($ph)";
            $params = $fechasSemana;
        } else {
            $where  = 'fecha >= ? AND fecha < ?';
            $params = [$inicio, $fin];
        }

        $where .= self::filtrosPosicionales($filtros, $params);

        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(DISTINCT rut_base) AS empleados,
                    COUNT(DISTINCT fecha)    AS dias_datos,
                    SUM(CASE WHEN estado='OK' THEN 1 ELSE 0 END) AS ok_total,
                    SUM(CASE WHEN estado='INCOMPLETO' THEN 1 ELSE 0 END) AS inc_total,
                    SUM(CASE WHEN estado='OBSERVADO' THEN 1 ELSE 0 END) AS obs_total,
                    SUM(CASE WHEN estado='ERROR' THEN 1 ELSE 0 END) AS err_total
               FROM marcaciones_resumen WHERE $where"
        );
        $stmt->execute($params);

        return $stmt->fetch() ?: [
            'empleados' => 0, 'dias_datos' => 0,
            'ok_total' => 0, 'inc_total' => 0, 'obs_total' => 0, 'err_total' => 0,
        ];
    }

    /** Presentes del día (vista día). */
    public static function presentes(string $fecha, array $filtros): array
    {
        [$where, $params] = self::filtrosBase(
            $filtros,
            'fecha = :f',
            ':f',
            $fecha
        );

        $stmt = Database::pdo()->prepare(
            'SELECT id, rut_base, numero, nombre, dpto, entrada, salida, total_horas,
                    cantidad_marcaciones, estado, observacion, editado_manual
               FROM marcaciones_resumen WHERE ' . $where . ' ORDER BY nombre ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Ausentes del día: empleados con datos en el mes pero sin marca en $fecha. */
    public static function ausentes(string $fecha, string $inicio, string $fin, array $filtros): array
    {
        if (($filtros['estado'] ?? '') !== '') {
            return [];
        }

        [$where, $params] = self::filtrosBase(
            $filtros,
            "fecha >= :m_start AND fecha < :m_end
                AND rut_base IS NOT NULL
                AND rut_base NOT IN (
                    SELECT rut_base FROM marcaciones_resumen WHERE fecha = :f AND rut_base IS NOT NULL
                )",
            ':m_start',
            $inicio,
            ':m_end',
            $fin,
            ':f',
            $fecha
        );

        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT rut_base, nombre, dpto, numero FROM marcaciones_resumen
              WHERE ' . $where . ' ORDER BY nombre ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Matriz semanal: empleados + datos por (rut, fecha).
     *
     * @param list<string> $fechasSem
     * @return array{emps: list<array>, data: array<string, array<string, array>>, ruts: list<string>}
     */
    public static function matrizSemana(array $fechasSem, array $filtros): array
    {
        $ph     = implode(',', array_fill(0, count($fechasSem), '?'));
        $params = $fechasSem;
        $where  = "fecha IN ($ph)";
        $where .= self::filtrosPosicionales($filtros, $params);

        $stmt = Database::pdo()->prepare(
            'SELECT id, fecha, rut_base, nombre, dpto, numero, entrada, salida,
                    total_horas, cantidad_marcaciones, estado
               FROM marcaciones_resumen WHERE ' . $where . ' ORDER BY nombre ASC, fecha ASC'
        );
        $stmt->execute($params);

        $emps = [];
        $data = [];
        foreach ($stmt->fetchAll() as $r) {
            $k = $r['rut_base'];
            if (!isset($emps[$k])) {
                $emps[$k] = ['nombre' => $r['nombre'], 'dpto' => $r['dpto'], 'numero' => $r['numero'], 'rut' => $k];
            }
            $data[$k][$r['fecha']] = $r;
        }
        usort($emps, static fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $stmtRuts = Database::pdo()->prepare(
            "SELECT DISTINCT rut_base FROM marcaciones_resumen WHERE fecha IN ($ph) AND rut_base IS NOT NULL"
        );
        $stmtRuts->execute($fechasSem);
        $ruts = array_values(array_filter(
            $stmtRuts->fetchAll(\PDO::FETCH_COLUMN),
            static fn ($rut) => $rut !== null && $rut !== ''
        ));

        return ['emps' => $emps, 'data' => $data, 'ruts' => $ruts];
    }

    /** Inactivos de la semana: con datos en el mes pero sin marcas en la semana. */
    public static function ausentesSemana(array $fechasSem, string $inicio, string $fin, array $filtros): array
    {
        $todosRutsSem = self::matrizSemana($fechasSem, $filtros)['ruts'];

        $params = [$inicio, $fin];
        $where  = 'fecha >= ? AND fecha < ?';
        if (!empty($todosRutsSem)) {
            $ph2    = implode(',', array_fill(0, count($todosRutsSem), '?'));
            $where .= " AND rut_base NOT IN ($ph2)";
            $params = array_merge($params, $todosRutsSem);
        }
        $where .= self::filtrosPosicionales($filtros, $params, false);

        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT rut_base, nombre, dpto, numero FROM marcaciones_resumen WHERE ' . $where . ' ORDER BY nombre'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Matriz mensual completa.
     *
     * @return array{emps: list<array>, data: array<string, array<string, array>>, ausentes: list<array>}
     */
    public static function matrizMes(string $inicio, string $fin, array $filtros): array
    {
        [$where, $params] = self::filtrosBase(
            $filtros,
            'fecha >= :mes_matm_start AND fecha < :mes_matm_end',
            ':mes_matm_start',
            $inicio,
            ':mes_matm_end',
            $fin
        );

        $stmt = Database::pdo()->prepare(
            'SELECT id, fecha, rut_base, nombre, dpto, numero, entrada, salida,
                    total_horas, cantidad_marcaciones, estado
               FROM marcaciones_resumen WHERE ' . $where . ' ORDER BY nombre ASC, fecha ASC'
        );
        $stmt->execute($params);

        $emps = [];
        $data = [];
        foreach ($stmt->fetchAll() as $r) {
            $k = $r['rut_base'];
            if (!isset($emps[$k])) {
                $emps[$k] = ['nombre' => $r['nombre'], 'dpto' => $r['dpto'], 'numero' => $r['numero'], 'rut' => $k];
            }
            $data[$k][$r['fecha']] = $r;
        }
        usort($emps, static fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $rutsMes = Database::pdo()->prepare(
            'SELECT DISTINCT rut_base FROM marcaciones_resumen
              WHERE fecha >= :mi AND fecha < :mf AND rut_base IS NOT NULL'
        );
        $rutsMes->execute([':mi' => $inicio, ':mf' => $fin]);
        $todosRutsMes = array_values(array_filter(
            $rutsMes->fetchAll(\PDO::FETCH_COLUMN),
            static fn ($rut) => $rut !== null && $rut !== ''
        ));

        $ausentes = [];
        if (!empty($todosRutsMes)) {
            $phAus      = implode(',', array_fill(0, count($todosRutsMes), '?'));
            $paramsAus  = $todosRutsMes;
            $whereAus   = "rut_base IS NOT NULL AND rut_base NOT IN ($phAus)";
            $whereAus  .= self::filtrosPosicionales($filtros, $paramsAus, false);

            $stmtAus = Database::pdo()->prepare(
                'SELECT DISTINCT rut_base, nombre, dpto, numero FROM marcaciones_resumen WHERE ' . $whereAus . ' ORDER BY nombre'
            );
            $stmtAus->execute($paramsAus);
            $ausentes = $stmtAus->fetchAll();
        }

        return ['emps' => $emps, 'data' => $data, 'ausentes' => $ausentes];
    }

    /* ─── Helpers de filtros ─────────────────────────────────── */

    /**
     * Construye WHERE + params con placeholders nombrados.
     * Los primeros argumentos son pares (placeholder, valor) de la condición base.
     */
    private static function filtrosBase(array $filtros, string $where, string ...$base): array
    {
        $params = [];
        $count  = count($base);
        for ($i = 0; $i < $count; $i += 2) {
            $params[$base[$i]] = $base[$i + 1];
        }

        $where .= self::filtrosNombrados($filtros, $params);

        return [$where, $params];
    }

    /** Agrega condiciones por dpto/estado/q usando placeholders nombrados (sufijos). */
    private static function filtrosNombrados(array $filtros, array &$params): string
    {
        $where = '';

        if (($filtros['dpto'] ?? '') !== '') {
            $where .= ' AND dpto = :dpto';
            $params[':dpto'] = $filtros['dpto'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $where .= ' AND estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (($filtros['q'] ?? '') !== '') {
            $q = $filtros['q'];
            $where .= ' AND (nombre LIKE :q1 OR rut_base LIKE :q2 OR numero LIKE :q3)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }

        return $where;
    }

    /** Igual que filtrosNombrados pero con placeholders posicionales. */
    private static function filtrosPosicionales(array $filtros, array &$params, bool $incluirEstado = true): string
    {
        $where = '';

        if (($filtros['dpto'] ?? '') !== '') {
            $where .= ' AND dpto = ?';
            $params[] = $filtros['dpto'];
        }
        if ($incluirEstado && ($filtros['estado'] ?? '') !== '') {
            $where .= ' AND estado = ?';
            $params[] = $filtros['estado'];
        }
        if (($filtros['q'] ?? '') !== '') {
            $q = $filtros['q'];
            $where .= ' AND (nombre LIKE ? OR rut_base LIKE ? OR numero LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        return $where;
    }

    public static function eliminarPorMes(string $inicio, string $fin): int
    {
        $stmt = Database::pdo()->prepare('DELETE FROM marcaciones_resumen WHERE fecha >= :inicio AND fecha <= :fin');
        $stmt->execute([':inicio' => $inicio, ':fin' => $fin]);

        return $stmt->rowCount();
    }
}
