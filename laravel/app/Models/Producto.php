<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo. En pantalla: Tratamiento. Especificación 3.3.
 */
class Producto extends Model
{
    protected $table = 'productos';

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
            'requiere_evaluacion' => 'boolean',
        ];
    }
}
