<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * En pantalla: Paciente. Especificación 3.4.
 * vendedor_id NULL = paciente disponible (sin asesor).
 */
class Cliente extends Model
{
    protected $table = 'clientes';

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(Oportunidad::class, 'cliente_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'cliente_id');
    }
}
