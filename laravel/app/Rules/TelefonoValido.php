<?php

namespace App\Rules;

use App\Support\Telefono;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teléfono de Guatemala: 8 dígitos que empiezan con 2 a 9, ya normalizado (R4, sección 10).
 */
class TelefonoValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Telefono::esValido(Telefono::normalizar($value))) {
            $fail('Escriba un teléfono de Guatemala de 8 dígitos.');
        }
    }
}
