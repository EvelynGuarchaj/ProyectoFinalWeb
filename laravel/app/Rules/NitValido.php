<?php

namespace App\Rules;

use App\Support\Nit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Acepta CF o un NIT válido con su dígito verificador (R4, sección 10).
 */
class NitValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || ! (Nit::esConsumidorFinal($value) || Nit::esValido(Nit::normalizar($value)))) {
            $fail('Escriba un NIT válido (con su dígito verificador) o CF.');
        }
    }
}
