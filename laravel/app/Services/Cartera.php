<?php

namespace App\Services;

use App\Exceptions\CarteraException;
use App\Models\Cliente;
use App\Models\Oportunidad;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Asignar, reasignar y liberar pacientes con el candado de cartera (sección 3.10).
 *
 * Orden de candados, igual que la API: primero users, después clientes,
 * después oportunidades. El candado de users es la misma fila que bloquea
 * la API (SELECT id FROM users WHERE id = ? FOR UPDATE), así el panel y la
 * API se ponen en fila para el mismo asesor.
 */
class Cartera
{
    public const MAXIMO = 10;

    public const ASESOR_NO_VALIDO = 'Elija un asesor activo de su equipo.';

    public const CARTERA_LLENA = 'Ese asesor ya tiene 10 pacientes, el máximo permitido.';

    /**
     * Asigna un paciente disponible o lo reasigna. Al reasignar, los planes
     * abiertos pasan al nuevo asesor; los cerrados conservan el suyo (sección 5).
     *
     * @throws CarteraException asesor no válido o cartera llena (campo vendedor_id)
     * @throws ModelNotFoundException el paciente no existe o el actor no lo ve (404)
     */
    public function asignar(Cliente $cliente, User $vendedor, User $actor): void
    {
        DB::transaction(function () use ($cliente, $vendedor, $actor) {
            $asesor = $this->bloquearAsesor($vendedor->id, $actor);
            $paciente = $this->bloquearPaciente($cliente->id, $actor);

            if ($paciente->vendedor_id === $asesor->id) {
                return; // Ya es suyo: no cuenta ni toca planes.
            }

            $this->comprobarEspacio($asesor);

            $paciente->forceFill(['vendedor_id' => $asesor->id])->save();

            Oportunidad::where('cliente_id', $paciente->id)
                ->whereIn('etapa', Oportunidad::ABIERTAS)
                ->update(['vendedor_id' => $asesor->id]);
        });
    }

    /**
     * Bloquea la fila del asesor de destino y comprueba que sea un asesor
     * activo y, para el coordinador, de su equipo. Siempre es el primer candado.
     */
    private function bloquearAsesor(int $vendedorId, User $actor): User
    {
        $asesor = User::whereKey($vendedorId)->lockForUpdate()->first();

        if (! $asesor
            || ! $asesor->esVendedor()
            || ! $asesor->activo
            || ($actor->esSupervisor() && $asesor->supervisor_id !== $actor->id)) {
            throw new CarteraException(self::ASESOR_NO_VALIDO, 'vendedor_id');
        }

        return $asesor;
    }

    /**
     * Bloquea la fila del paciente por id (lee el vendedor_id más reciente)
     * y comprueba que el actor lo pueda ver. Lo ajeno responde 404 (S5).
     */
    private function bloquearPaciente(int $clienteId, User $actor): Cliente
    {
        $paciente = Cliente::whereKey($clienteId)->lockForUpdate()->firstOrFail();

        if (! $actor->esAdmin() && ! $this->loVe($actor, $paciente)) {
            throw (new ModelNotFoundException)->setModel(Cliente::class, [$clienteId]);
        }

        return $paciente;
    }

    /** El coordinador ve los disponibles y los de los asesores de su equipo. */
    private function loVe(User $actor, Cliente $paciente): bool
    {
        if (! $actor->esSupervisor()) {
            return false;
        }

        return $paciente->vendedor_id === null
            || User::whereKey($paciente->vendedor_id)->value('supervisor_id') === $actor->id;
    }

    /** Cuenta la cartera con la fila del asesor ya bloqueada. */
    private function comprobarEspacio(User $asesor): void
    {
        if (Cliente::where('vendedor_id', $asesor->id)->count() >= self::MAXIMO) {
            throw new CarteraException(self::CARTERA_LLENA, 'vendedor_id');
        }
    }
}
