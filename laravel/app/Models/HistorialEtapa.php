<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de un cambio de etapa. Especificación 3.7.
 * Solo tiene created_at (DATETIME en UTC).
 */
class HistorialEtapa extends Model
{
    protected $table = 'historial_etapas';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class, 'oportunidad_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
