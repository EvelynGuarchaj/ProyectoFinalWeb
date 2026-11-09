<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /** vendedor_id no: lo asigna el spec 07, nunca el formulario del paciente. */
    protected $fillable = ['nombre', 'nit', 'telefono', 'correo'];

    /**
     * Pacientes que la persona del panel puede ver (S5). El administrador ve todos;
     * el coordinador, los de los asesores de su equipo (activos o no) y los disponibles.
     * Se usa con findOrFail(), así que lo ajeno responde 404.
     */
    public function scopeVisiblePara(Builder $query, User $u): Builder
    {
        if ($u->esAdmin()) {
            return $query;
        }

        if ($u->esSupervisor()) {
            return $query->where(fn (Builder $q) => $q
                ->whereNull('vendedor_id')
                ->orWhereIn('vendedor_id', User::select('id')
                    ->where('rol', 'vendedor')
                    ->where('supervisor_id', $u->id)));
        }

        // Expresión fija: nadie más ve pacientes desde el panel.
        return $query->whereRaw('1 = 0');
    }

    /** NIT para mostrar: "CF" cuando es NULL. */
    public function nitEnPantalla(): string
    {
        return $this->nit ?? 'CF';
    }

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
