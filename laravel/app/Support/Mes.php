<?php

namespace App\Support;

/**
 * Mes de una consulta ?mes=AAAA-MM (spec 09 y spec 14). Lo comparten "Mi equipo"
 * y el reporte del mes para que las dos páginas decidan igual.
 */
final class Mes
{
    public const PATRON = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public const NO_VALIDO = 'Elija un mes válido (AAAA-MM).';

    /** Mes actual en hora de Guatemala (UTC−6 fijo), 'AAAA-MM'. */
    public static function actual(): string
    {
        return now('America/Guatemala')->format('Y-m');
    }

    /**
     * Ausente o vacío → [mes actual, null]; con formato AAAA-MM → [ese mes, null];
     * cualquier otra cosa (incluido un arreglo) → [mes actual, aviso].
     *
     * @return array{0: string, 1: string|null}
     */
    public static function elegido(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [self::actual(), null];
        }

        if (is_string($valor) && preg_match(self::PATRON, $valor) === 1) {
            return [$valor, null];
        }

        return [self::actual(), self::NO_VALIDO];
    }
}
