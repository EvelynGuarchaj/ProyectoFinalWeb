<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Semilla de verificación: exactamente los datos de la sección 11 del enunciado.
 * Fechas en UTC. Se inserta directo con DB::table (sin validaciones del panel),
 * porque algunos datos son históricos (línea de un producto inactivo, precio
 * distinto del catálogo).
 */
class VerificacionSeeder extends Seeder
{
    /** Fecha final del historial de las oportunidades que siguen abiertas. */
    private const FIN_ABIERTAS = '2026-10-02 16:00:00';

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
        // Un solo hash bcrypt para las 7 cuentas.
        $hash = Hash::make('Ventas2026');

        $fila = fn (string $nombre, string $correo, string $rol, ?int $supervisorId, bool $activo) => [
            'name' => $nombre,
            'email' => $correo,
            'password' => $hash,
            'rol' => $rol,
            'supervisor_id' => $supervisorId,
            'activo' => $activo,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];

        $ids = [];

        // Primero administrador y supervisores (la FK y chk_users_supervisor
        // exigen que el supervisor exista antes que sus vendedores).
        $ids['admin@crm.test'] = DB::table('users')->insertGetId($fila('Alicia Morales', 'admin@crm.test', 'administrador', null, true));
        $ids['sergio@crm.test'] = DB::table('users')->insertGetId($fila('Sergio Ruiz', 'sergio@crm.test', 'supervisor', null, true));
        $ids['sandra@crm.test'] = DB::table('users')->insertGetId($fila('Sandra Pérez', 'sandra@crm.test', 'supervisor', null, true));

        $ids['valeria@crm.test'] = DB::table('users')->insertGetId($fila('Valeria López', 'valeria@crm.test', 'vendedor', $ids['sergio@crm.test'], true));
        $ids['diego@crm.test'] = DB::table('users')->insertGetId($fila('Diego Castillo', 'diego@crm.test', 'vendedor', $ids['sergio@crm.test'], true));
        $ids['carlos@crm.test'] = DB::table('users')->insertGetId($fila('Carlos Ajú', 'carlos@crm.test', 'vendedor', $ids['sandra@crm.test'], true));
        $ids['ivan@crm.test'] = DB::table('users')->insertGetId($fila('Iván Toj', 'ivan@crm.test', 'vendedor', $ids['sandra@crm.test'], false));

        return $ids;
    }

    /** @return array<string, int> nombre => id */
    private function productos(CarbonImmutable $ahora): array
    {
        $catalogo = [
            ['Plan básico', '1200.00', true],
            ['Plan avanzado', '2750.50', true],
            ['Instalación', '450.00', true],
            ['Soporte anual', '3600.00', true],
            ['Licencia anterior', '999.99', false],
        ];

        $ids = [];
        foreach ($catalogo as [$nombre, $precio, $activo]) {
            $ids[$nombre] = DB::table('productos')->insertGetId([
                'nombre' => $nombre,
                'precio' => $precio,
                'activo' => $activo,
                'requiere_evaluacion' => false,
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
        // [correo del vendedor o null, nombre, NIT compacto o null (CF), teléfono, correo]
        $lista = [
            ['valeria@crm.test', 'Comercial El Quetzal', '4521817K', '55551234', 'compras@elquetzal.test'],
            ['valeria@crm.test', 'Ferretería Xela', '98765434', '77612345', null],
            ['valeria@crm.test', 'María Coyoy', null, '41234567', null],

            ['diego@crm.test', 'Farmacia Los Altos', '34567895', '77634567', 'gerencia@losaltos.test'],
            ['diego@crm.test', 'Luis Tzul', null, '52345678', null],

            ['carlos@crm.test', 'Abarrotes San Juan', '12345679', '77651001', null],
            ['carlos@crm.test', 'Panadería La Esperanza', null, '30001002', null],
            ['carlos@crm.test', 'Taller El Volcán', '22233342', '77651003', null],
            ['carlos@crm.test', 'Ana Xicará', null, '30001004', null],
            ['carlos@crm.test', 'Librería Cantel', '55566677', '77651005', null],
            ['carlos@crm.test', 'Pedro Batz', null, '30001006', null],
            ['carlos@crm.test', 'Tienda La Bendición', null, '30001007', null],
            ['carlos@crm.test', 'Distribuidora Occidente', '77788893', '77651008', null],
            ['carlos@crm.test', 'Rosa Tax', null, '30001009', null],

            ['ivan@crm.test', 'Hotel Los Pinos', '66655544', '77661001', null],

            [null, 'Agencia Cuatro Caminos', null, '30002001', null],
            [null, 'Café Zunil', null, '30002002', null],
            [null, 'Clínica San Rafael', '8889990K', '77672003', null],
            [null, 'Constructora Altiplano', '44433328', '77672004', null],
            [null, 'Escuela Futuro', null, '30002005', null],
            [null, 'Gimnasio Fuerza', null, '30002006', null],
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
        // Recorridos de etapas (sin retrocesos).
        $ganada = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada'];
        $perdidaEnPropuesta = ['prospecto', 'calificado', 'propuesta', 'perdida'];

        // [título, cliente, vendedor, recorrido, cierre UTC o null, motivo, líneas [producto, cantidad, precio guardado]]
        $lista = [
            ['Renovación de planes', 'Comercial El Quetzal', 'valeria@crm.test', $ganada, '2026-10-05 16:00:00', null,
                [['Plan avanzado', 2, '2600.00'], ['Instalación', 1, '450.00']]],
            ['Equipo para sucursal', 'Ferretería Xela', 'valeria@crm.test', $ganada, '2026-11-01 03:30:00', null,
                [['Plan básico', 3, '1200.00']]],
            ['Soporte del hogar', 'María Coyoy', 'valeria@crm.test', $ganada, '2026-11-01 06:30:00', null,
                [['Soporte anual', 1, '3600.00']]],
            ['Ampliación a bodega', 'Comercial El Quetzal', 'valeria@crm.test', array_slice($ganada, 0, 4), null, null,
                [['Plan avanzado', 1, '2750.50']]],
            ['Capacitación de personal', 'Ferretería Xela', 'valeria@crm.test', array_slice($ganada, 0, 3), null, null,
                [['Instalación', 2, '450.00']]],
            ['Plan familiar', 'María Coyoy', 'valeria@crm.test', $perdidaEnPropuesta, '2026-10-20 18:00:00', 'Eligió a la competencia',
                [['Plan básico', 1, '1200.00'], ['Licencia anterior', 1, '999.99']]],
            ['Seguimiento anual', 'Comercial El Quetzal', 'valeria@crm.test', ['prospecto'], null, null,
                []],
            ['Licencias de caja', 'Farmacia Los Altos', 'diego@crm.test', $ganada, '2026-10-10 15:00:00', null,
                [['Plan básico', 2, '1200.00'], ['Soporte anual', 1, '3600.00']]],
            ['Mantenimiento', 'Luis Tzul', 'diego@crm.test', array_slice($ganada, 0, 2), null, null,
                [['Instalación', 1, '450.00']]],
            ['Plan anual', 'Abarrotes San Juan', 'carlos@crm.test', $ganada, '2026-10-15 20:00:00', null,
                [['Soporte anual', 2, '3600.00']]],
            ['Cotización de equipo', 'Taller El Volcán', 'carlos@crm.test', array_slice($ganada, 0, 4), null, null,
                [['Plan avanzado', 2, '2750.50']]],
        ];

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

            foreach ($lineas as [$producto, $cantidad, $precio]) {
                DB::table('oportunidad_productos')->insert([
                    'oportunidad_id' => $oportunidadId,
                    'producto_id' => $productos[$producto],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
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
        // [cliente, vendedor, tipo, descripción, vence UTC, completada]
        $lista = [
            ['Comercial El Quetzal', 'valeria@crm.test', 'llamada', 'Confirmar fecha de instalación', '2026-10-08 15:00:00', true],
            ['Ferretería Xela', 'valeria@crm.test', 'tarea', 'Enviar propuesta de capacitación', '2030-01-15 15:00:00', false],
            ['María Coyoy', 'valeria@crm.test', 'reunion', 'Presentar opciones de soporte', '2026-09-01 15:00:00', false],
            ['Farmacia Los Altos', 'diego@crm.test', 'llamada', 'Renovación de licencias', '2030-02-01 15:00:00', false],
        ];

        foreach ($lista as [$cliente, $vendedor, $tipo, $descripcion, $vence, $completada]) {
            DB::table('actividades')->insert([
                'cliente_id' => $clientes[$cliente],
                'vendedor_id' => $usuarios[$vendedor],
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'vence_en' => $vence,
                'completada' => $completada,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }
}
