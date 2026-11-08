<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de cartera no se cumple (spec 07, sección 2.1).
 * Con $campo, el controlador muestra el mensaje junto a ese campo;
 * sin campo, como mensaje de la página.
 */
final class CarteraException extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $campo = null)
    {
        parent::__construct($mensaje);
    }
}
