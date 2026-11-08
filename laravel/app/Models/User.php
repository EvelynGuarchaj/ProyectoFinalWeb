<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Cuenta del sistema. En pantalla: Administrador, Coordinador (supervisor)
 * o Asesor (vendedor). Especificación 3.2.
 */
#[Fillable(['name', 'email', 'password', 'rol', 'supervisor_id', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'administrador';
    }

    public function esSupervisor(): bool
    {
        return $this->rol === 'supervisor';
    }

    public function esVendedor(): bool
    {
        return $this->rol === 'vendedor';
    }

    /** Coordinador de este asesor. */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** Asesores que coordina este supervisor. */
    public function vendedores(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    /** Cartera del asesor. */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'vendedor_id');
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(Oportunidad::class, 'vendedor_id');
    }
}
