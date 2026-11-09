<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /** Etapa => nombre en pantalla (vocabulario dental, sección 1.3). */
    public const ETAPAS_EN_PANTALLA = [
        'prospecto' => 'Interesado',
        'calificado' => 'Evaluado',
        'propuesta' => 'Presupuesto enviado',
        'negociacion' => 'En negociación',
        'ganada' => 'Aceptado',
        'perdida' => 'Rechazado',
    ];

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

    /**
     * Planes que la persona del panel puede ver (L5, S5). El administrador ve todos;
     * el coordinador, los de los asesores de su equipo (activos o no), según
     * oportunidades.vendedor_id. Se usa con findOrFail(): lo ajeno responde 404.
     */
    public function scopeVisiblePara(Builder $query, User $u): Builder
    {
        if ($u->esAdmin()) {
            return $query;
        }

        if ($u->esSupervisor()) {
            return $query->whereIn('oportunidades.vendedor_id', User::select('id')
                ->where('rol', 'vendedor')
                ->where('supervisor_id', $u->id));
        }

        // Expresión fija: nadie más ve planes desde el panel.
        return $query->whereRaw('1 = 0');
    }

    /**
     * Agrega la columna total = SUM(cantidad * precio_unitario) de sus líneas,
     * calculada en MySQL (withSum no multiplica). Sale como texto DECIMAL;
     * un plan sin líneas da 0.00. Spec 12, sección 2.2.
     */
    public function scopeConTotal(Builder $query): Builder
    {
        return $query
            ->select('oportunidades.*')
            ->addSelect(['total' => OportunidadProducto::selectRaw('COALESCE(SUM(cantidad * precio_unitario), 0)')
                ->whereColumn('oportunidad_productos.oportunidad_id', 'oportunidades.id')]);
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
