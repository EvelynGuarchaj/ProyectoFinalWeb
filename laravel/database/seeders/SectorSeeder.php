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
        // [correo del asesor o null, nombre, NIT compacto o null (CF), teléfono, correo]
        // NIT y teléfonos validados con App\Support\Nit y App\Support\Telefono;
        // ninguno se repite con VerificacionSeeder.
        $lista = [
            ['andrea@dental.test', 'Sofía Ramírez', null, '48101101', 'sofia.ramirez@correo.test'],
            ['andrea@dental.test', 'Jorge Cifuentes', '10293841', '48101102', null],
            ['andrea@dental.test', 'Colegio Bilingüe Los Altos', '56473826', '77681103', 'administracion@colegiolosaltos.test'],
            ['andrea@dental.test', 'Mariela Chun', null, '48101104', null],
            ['andrea@dental.test', 'Rodrigo Tzoc', '91827361', '48101105', null],
            ['andrea@dental.test', 'Gabriela Barrios', null, '48101106', null],

            ['kevin@dental.test', 'Esteban Quemé', '33445567', '51201201', null],
            ['kevin@dental.test', 'Paola Fernández', null, '51201202', null],
            ['kevin@dental.test', 'Textiles del Altiplano', '70123454', '77681204', null],
            ['kevin@dental.test', 'Héctor Sapón', '24681350', '51201205', null],
            ['kevin@dental.test', 'Claudia Ixcot', null, '51201206', null],
            // Paciente para R-P6: plan en negociación con implante y sin citas (decisión D2).
            ['kevin@dental.test', 'Marco Ajanel', '48151629', '51201207', null],

            [null, 'Fernando Pú', '81357923', '51301301', null],
            [null, 'Daniela Monterroso', null, '51301302', null],
            [null, 'Andrés Velásquez', '60708093', '51301303', null],
        ];

        $ids = [];
        foreach ($lista as [$vendedor, $nombre, $nit, $telefono, $correo]) {
            $ids[$nombre] = DB::table('clientes')->insertGetId([
                'vendedor_id' => $vendedor === null ? null : $usuarios[$vendedor],
                'nombre' => $nombre,
                'nit' => $nit,
                'telefono' => $telefono,
                'correo' => $correo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $usuarios
     * @param  array<string, int>  $productos
     * @param  array<string, int>  $clientes
     */
    private function oportunidades(array $usuarios, array $productos, array $clientes): void
    {
        // Recorridos de etapas (sin retrocesos). Las abiertas usan el prefijo
        // de $ganada hasta su etapa.
        $ganada = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada'];
        $perdidaEnNegociacion = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'perdida'];
        $hasta = fn (int $pasos) => array_slice($ganada, 0, $pasos);

        // [título, paciente, asesor, recorrido, cierre UTC o null, motivo, líneas [tratamiento, cantidad]]
        $lista = [
            ['Ortodoncia completa', 'Sofía Ramírez', 'andrea@dental.test', $hasta(2), null, null,
                [['Ortodoncia con brackets', 1]]],
            ['Implante de molar', 'Jorge Cifuentes', 'andrea@dental.test', $hasta(4), null, null,
                [['Implante dental', 1]]],
            ['Limpieza para docentes', 'Colegio Bilingüe Los Altos', 'andrea@dental.test', $ganada, '2026-10-20 16:00:00', null,
                [['Limpieza dental', 25]]],
            ['Blanqueamiento', 'Mariela Chun', 'andrea@dental.test', $hasta(3), null, null,
                [['Blanqueamiento', 1]]],
            ['Endodoncia y corona', 'Rodrigo Tzoc', 'andrea@dental.test', $perdidaEnNegociacion, '2026-10-28 18:00:00', 'Decidió tratarse en otra clínica',
                [['Endodoncia', 1], ['Corona de porcelana', 1]]],
            ['Primera consulta', 'Gabriela Barrios', 'andrea@dental.test', $hasta(1), null, null,
                []],
            ['Resinas', 'Esteban Quemé', 'kevin@dental.test', $ganada, '2026-11-03 17:00:00', null,
                [['Resina dental', 3]]],
            ['Extracción de cordales', 'Paola Fernández', 'kevin@dental.test', $hasta(4), null, null,
                [['Extracción de cordal', 2]]],
            ['Limpieza anual', 'Textiles del Altiplano', 'kevin@dental.test', $hasta(2), null, null,
                [['Limpieza dental', 12]]],
            ['Implante dental', 'Héctor Sapón', 'kevin@dental.test', $hasta(2), null, null,
                [['Implante dental', 1]]],
            ['Ortodoncia', 'Claudia Ixcot', 'kevin@dental.test', $hasta(1), null, null,
                [['Ortodoncia con brackets', 1]]],
            // Plan anterior a que el implante se marcara como "requiere evaluación"
            // (decisión D2): permite demostrar R-P6.
            ['Implante de premolar', 'Marco Ajanel', 'kevin@dental.test', $hasta(4), null, null,
                [['Implante dental', 1]]],
        ];

        // El precio de la línea se copia del catálogo al agregar.
        $precios = DB::table('productos')->whereIn('id', $productos)->pluck('precio', 'id');

        foreach ($lista as [$titulo, $cliente, $vendedor, $recorrido, $cierre, $motivo, $lineas]) {
            $vendedorId = $usuarios[$vendedor];

            // Fechas del historial: cada 3 días hacia atrás desde la fecha final.
            $fin = CarbonImmutable::parse($cierre ?? self::FIN_ABIERTAS, 'UTC');
            $pasos = count($recorrido);
            $fechas = [];
            for ($i = 0; $i < $pasos; $i++) {
                $fechas[] = $fin->subDays(self::DIAS_ENTRE_REGISTROS * ($pasos - 1 - $i));
            }
            $creacion = $fechas[0];

            $oportunidadId = DB::table('oportunidades')->insertGetId([
                'cliente_id' => $clientes[$cliente],
                'vendedor_id' => $vendedorId,
                'titulo' => $titulo,
                'etapa' => end($recorrido),
                'motivo_perdida' => $motivo,
                'fecha_cierre' => $cierre,
                'created_at' => $creacion,
                'updated_at' => $fin,
            ]);

            foreach ($lineas as [$producto, $cantidad]) {
                DB::table('oportunidad_productos')->insert([
                    'oportunidad_id' => $oportunidadId,
                    'producto_id' => $productos[$producto],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precios[$productos[$producto]],
                    'created_at' => $creacion,
                    'updated_at' => $creacion,
                ]);
            }

            $anterior = null;
            foreach ($recorrido as $i => $etapa) {
                DB::table('historial_etapas')->insert([
                    'oportunidad_id' => $oportunidadId,
                    'usuario_id' => $vendedorId,
                    'etapa_anterior' => $anterior,
                    'etapa_nueva' => $etapa,
                    'created_at' => $fechas[$i],
                ]);
                $anterior = $etapa;
            }
        }
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
