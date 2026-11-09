<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        // Un solo hash para las 3 cuentas, con la contraseña pública que
        // define VerificacionSeeder (decisión D3: el texto vive en un solo lugar).
        $hash = Hash::make(VerificacionSeeder::CONTRASENA);

        $fila = fn (string $nombre, string $correo, string $rol, ?int $supervisorId) => [
            'name' => $nombre,
            'email' => $correo,
            'password' => $hash,
            'rol' => $rol,
            'supervisor_id' => $supervisorId,
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];

        $ids = [];

        // Primero la coordinadora: la FK y chk_users_supervisor exigen que
        // exista antes que sus asesores.
        $ids['lucia@dental.test'] = DB::table('users')->insertGetId($fila('Dra. Lucía Méndez', 'lucia@dental.test', 'supervisor', null));

        $ids['andrea@dental.test'] = DB::table('users')->insertGetId($fila('Andrea Gómez', 'andrea@dental.test', 'vendedor', $ids['lucia@dental.test']));
        $ids['kevin@dental.test'] = DB::table('users')->insertGetId($fila('Kevin Chávez', 'kevin@dental.test', 'vendedor', $ids['lucia@dental.test']));

        return $ids;
    }

    /** @return array<string, int> nombre => id */
    private function productos(CarbonImmutable $ahora): array
    {
        // [nombre, precio (texto, para no pasar por float), requiere evaluación]
        $catalogo = [
            ['Limpieza dental', '300.00', false],
            ['Resina dental', '350.00', false],
            ['Extracción simple', '250.00', false],
            ['Extracción de cordal', '900.00', false],
            ['Blanqueamiento', '1200.00', false],
            ['Endodoncia', '1500.00', false],
            ['Corona de porcelana', '2500.00', false],
            ['Ortodoncia con brackets', '8000.00', true],
            ['Implante dental', '9000.00', true],
        ];

        $ids = [];
        foreach ($catalogo as [$nombre, $precio, $requiereEvaluacion]) {
            $ids[$nombre] = DB::table('productos')->insertGetId([
                'nombre' => $nombre,
                'precio' => $precio,
                'activo' => true,
                'requiere_evaluacion' => $requiereEvaluacion,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
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
