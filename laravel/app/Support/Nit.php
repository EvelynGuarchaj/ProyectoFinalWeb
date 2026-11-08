<?php

namespace App\Support;

/**
 * NIT de Guatemala. R4 del enunciado y sección 10 de la especificación.
 * Debe dar exactamente lo mismo que src/util/nit.js de la API.
 */
final class Nit
{
    /**
     * Quita espacios y guiones, pasa a mayúsculas y quita los ceros a la izquierda.
     * '452 1817-k' → '4521817K'; '00012345679' → '12345679'; 'cf' → 'CF'.
     */
    public static function normalizar(string $texto): string
    {
        $compacto = mb_strtoupper(preg_replace('/[\s\-]+/u', '', $texto));

        return ltrim($compacto, '0');
    }

    /** 'cf', 'CF' o 'C F' → true. */
    public static function esConsumidorFinal(string $texto): bool
    {
        return self::normalizar($texto) === 'CF';
    }

    /**
     * Recibe el NIT ya normalizado. Entre 2 y 12 caracteres, cuerpo solo dígitos
     * y verificador (0-9 o K) calculado con módulo 11.
     */
    public static function esValido(string $compacto): bool
    {
        $largo = strlen($compacto);
        if ($largo < 2 || $largo > 12) {
            return false;
        }

        $cuerpo = substr($compacto, 0, -1);
        $verificador = substr($compacto, -1);

        if (! ctype_digit($cuerpo) || ! preg_match('/^[0-9K]$/', $verificador)) {
            return false;
        }

        // De derecha a izquierda, cada dígito por 2, 3, 4…
        $suma = 0;
        $factor = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * $factor;
            $factor++;
        }

        $resultado = (11 - ($suma % 11)) % 11;
        $esperado = $resultado === 10 ? 'K' : (string) $resultado;

        return $verificador === $esperado;
    }
}
