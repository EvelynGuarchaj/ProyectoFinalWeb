<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Formato de montos y fechas en pantalla, igual que quetzales() y fechaGT()
 * de util.js en la SPA. Especificación, sección 6 (reglas comunes del panel).
 */
final class Formato
{
    /** Meses abreviados, sin depender de APP_LOCALE ni de la extensión intl. */
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** Meses completos, para títulos como "Ventas de octubre 2026" (spec 09 y spec 14). */
    private const MESES_COMPLETOS = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    /**
     * '2026-10' → 'octubre 2026'. Recibe un mes ya validado con ^\d{4}-(0[1-9]|1[0-2])$.
     */
    public static function mesEnPalabras(string $mes): string
    {
        [$anio, $numero] = explode('-', $mes);

        return self::MESES_COMPLETOS[(int) $numero - 1].' '.$anio;
    }

    /**
     * '1200.00' → 'Q1,200.00'; 0 o null → 'Q0.00'.
     */
    public static function quetzales(string|int|float|null $monto): string
    {
        return 'Q'.number_format((float) ($monto ?? 0), 2, '.', ',');
    }

    /**
     * Fecha guardada en UTC → hora de Guatemala (UTC−6 fijo), ej. '31 oct 2026, 21:30'.
     * null → '—'.
     */
    public static function fechaGT(DateTimeInterface|string|null $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }

        $gt = ($fecha instanceof DateTimeInterface
            ? CarbonImmutable::instance($fecha)
            : CarbonImmutable::parse($fecha, 'UTC')
        )->setTimezone('-06:00');

        return $gt->format('j').' '.self::MESES[$gt->month - 1].' '.$gt->format('Y, H:i');
    }
}
