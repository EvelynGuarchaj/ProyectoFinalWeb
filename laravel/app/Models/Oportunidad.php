<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * En pantalla: Plan de tratamiento. Especificación 3.5.
 * El total no se guarda: SUM(cantidad * precio_unitario) en SQL.
 */
class Oportunidad extends Model
{
    protected $table = 'oportunidades';

    /** Todas las etapas, en orden. "negociacion" va sin tilde. */
    public const ETAPAS = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'];

    /** Etapas abiertas: las que cuentan para el pronóstico. */
    public const ABIERTAS = ['prospecto', 'calificado', 'propuesta', 'negociacion'];

    /**
     * Probabilidad por etapa, en porcentaje entero.
     * En SQL se usa como decimal (0.10, 0.25, 0.50, 0.75).
     */
    public const PROBABILIDAD = [
        'prospecto' => 10,
        'calificado' => 25,
        'propuesta' => 50,
        'negociacion' => 75,
        'ganada' => 100,
        'perdida' => 0,
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre' => 'datetime',
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

    public function lineas(): HasMany
    {
        return $this->hasMany(OportunidadProducto::class, 'oportunidad_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEtapa::class, 'oportunidad_id');
    }
}
