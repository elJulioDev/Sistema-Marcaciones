<?php

declare(strict_types=1);

/**
 * Helpers globales centralizados (antes se duplicaban en cada página PHP).
 * Sanitización, URLs, RUT chileno, fechas y conversiones horarias.
 */

if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) \App\Core\Env::get('BASE_URL', ''), '/');

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return base_url($path);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}

/* ── RUT chileno ─────────────────────────────────────────────── */

if (!function_exists('normalizar_rut')) {
    function normalizar_rut(mixed $rut): string
    {
        return str_replace(['.', '-', ' '], '', strtoupper(trim((string) $rut)));
    }
}

function rut_cuerpo(mixed $rut): string
{
    $rut = normalizar_rut($rut);

    return strlen($rut) < 2 ? '' : substr($rut, 0, -1);
}

function rut_dv(mixed $rut): string
{
    $rut = normalizar_rut($rut);

    return strlen($rut) < 2 ? '' : substr($rut, -1);
}

function validar_rut(mixed $rut): bool
{
    $rut = normalizar_rut($rut);

    if (!preg_match('/^[0-9]+[0-9K]$/', $rut)) {
        return false;
    }

    $cuerpo = rut_cuerpo($rut);
    $dv     = rut_dv($rut);

    $suma     = 0;
    $multiplo = 2;

    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += (int) $cuerpo[$i] * $multiplo;
        $multiplo = $multiplo > 7 ? 2 : $multiplo + 1;
    }

    $calc     = 11 - ($suma % 11);
    $esperado = $calc === 11 ? '0' : ($calc === 10 ? 'K' : (string) $calc);

    return $dv === $esperado;
}

function formatear_rut(mixed $rut): string
{
    $rut = normalizar_rut($rut);
    if (strlen($rut) < 2) {
        return $rut;
    }

    $cuerpo = substr($rut, 0, -1);
    $dv     = substr($rut, -1);

    return strrev(implode('.', str_split(strrev($cuerpo), 3))) . '-' . $dv;
}

/* ── Fechas ──────────────────────────────────────────────────── */

function nombre_dia_es(string $fechaYmd): string
{
    static $dias = [
        'Sunday' => 'Domingo',
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
    ];

    $dia = date('l', strtotime($fechaYmd));

    return $dias[$dia] ?? '';
}

/* ── Conversión horaria ──────────────────────────────────────── */

function hms_a_minutos(mixed $hms): int
{
    if ($hms === null || $hms === '') {
        return 0;
    }

    [$h, $m] = array_pad(explode(':', trim((string) $hms)), 2, 0);

    return ((int) $h * 60) + (int) $m;
}

function minutos_a_hhmm_display(int $minutos): string
{
    if ($minutos <= 0) {
        return '0h 00m';
    }

    return floor($minutos / 60) . 'h ' . str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT) . 'm';
}

function minutos_a_time(int $minutos): string
{
    return sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);
}

function normalizar_hora(mixed $hora): string|false|null
{
    $hora = trim((string) $hora);
    if ($hora === '') {
        return null;
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return false;
    }

    return $hora . ':00';
}
