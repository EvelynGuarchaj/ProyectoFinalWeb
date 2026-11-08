<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * En pantalla: Cita. Especificación 3.8.
 */
class Actividad extends Model
{
    protected $table = 'actividades';

    protected function casts(): array
    {
        return [
            'vence_en' => 'datetime',
            'completada' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }
}
