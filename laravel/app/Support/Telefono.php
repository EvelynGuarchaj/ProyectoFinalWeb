<?php

namespace App\Support;

/**
 * Teléfono de Guatemala. R4 del enunciado y sección 10 de la especificación.
 * Debe dar exactamente lo mismo que src/util/telefono.js de la API.
 */
final class Telefono
{
    /**
     * Quita espacios, guiones, puntos y paréntesis; quita '+502' del inicio;
     * quita '502' del inicio solo si después quedan exactamente 8 caracteres.
     * '+502 5555-1234' → '55551234'; '5021 2345' → '50212345'.
     */
    public static function normalizar(string $texto): string
    {
        $limpio = preg_replace('/[\s\-.()]+/u', '', $texto);

        if (str_starts_with($limpio, '+502')) {
            return substr($limpio, 4);
        }

        if (str_starts_with($limpio, '502') && strlen($limpio) === 11) {
            return substr($limpio, 3);
        }

        return $limpio;
    }

    /** Recibe el teléfono ya normalizado: 8 dígitos que empiezan con 2 a 9. */
    public static function esValido(string $normalizado): bool
    {
        return preg_match('/^[2-9][0-9]{7}$/', $normalizado) === 1;
    }
}
