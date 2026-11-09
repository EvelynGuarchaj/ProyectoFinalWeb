<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semilla del sector dental (spec 17-semilla-sector).
 * Corre después de VerificacionSeeder y solo usa sus propias cuentas y
 * pacientes (decisión D4), para no cambiar las cifras de control.
 * Fechas en UTC. Se inserta directo con DB::table, igual que la semilla
 * de verificación, para conservar las fechas históricas.
 */
class SectorSeeder extends Seeder
{
    /** Fecha final del historial de los planes que siguen abiertos. */
    private const FIN_ABIERTAS = '2026-11-02 16:00:00';

    /** Días entre dos registros de historial. */
    private const DIAS_ENTRE_REGISTROS = 3;

    public function run(): void
    {
        $ahora = CarbonImmutable::now('UTC');

        DB::transaction(function () use ($ahora) {
            $usuarios = $this->cuentas($ahora);
            $productos = $this->productos($ahora);
            $clientes = $this->clientes($usuarios, $ahora);
            $this->oportunidades($usuarios, $productos, $clientes);
            $this->actividades($usuarios, $clientes, $ahora);
        });
    }

    /** @return array<string, int> correo => id */
    private function cuentas(CarbonImmutable $ahora): array
    {
        return [];
    }

    /** @return array<string, int> nombre => id */
    private function productos(CarbonImmutable $ahora): array
    {
        return [];
    }

    /**
     * @param  array<string, int>  $usuarios
     * @return array<string, int> nombre => id
     */
    private function clientes(array $usuarios, CarbonImmutable $ahora): array
    {
        return [];
    }

    /**
     * @param  array<string, int>  $usuarios
     * @param  array<string, int>  $productos
     * @param  array<string, int>  $clientes
     */
    private function oportunidades(array $usuarios, array $productos, array $clientes): void
    {
        //
    }

    /**
     * @param  array<string, int>  $usuarios
     * @param  array<string, int>  $clientes
     */
    private function actividades(array $usuarios, array $clientes, CarbonImmutable $ahora): void
    {
        //
    }
}
