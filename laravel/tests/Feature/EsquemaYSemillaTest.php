<?php

namespace Tests\Feature;

use Database\Seeders\VerificacionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Spec 01 · Esquema y semilla de verificación.
 * Corre contra MySQL (crm_test): las CHECK y REGEXP no existen en SQLite.
 */
class EsquemaYSemillaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase migra una sola vez por corrida (y si otra clase migró
     * antes, ya no siembra). Por eso se siembra aquí, dentro de la transacción
     * de cada prueba: al terminar se revierte y la base queda vacía.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
    }

    private const TABLAS = [
        'users', 'productos', 'clientes', 'oportunidades',
        'oportunidad_productos', 'historial_etapas', 'actividades',
    ];

    // ---------------------------------------------------------------
    // CA1 · Esquema completo
    // ---------------------------------------------------------------

    public function test_ca1_existen_las_siete_tablas(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(DB::getSchemaBuilder()->hasTable($tabla), "Falta la tabla {$tabla}");
        }
    }

    public function test_ca1_existen_las_nueve_check_con_su_nombre(): void
    {
        $nombres = collect(DB::select(
            'SELECT CONSTRAINT_NAME AS nombre FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()'
        ))->pluck('nombre')->sort()->values()->all();

        $this->assertSame([
            'chk_clientes_nit',
            'chk_clientes_telefono',
            'chk_lineas_cantidad',
            'chk_lineas_precio',
            'chk_oportunidades_cierre',
            'chk_oportunidades_motivo',
            'chk_oportunidades_motivo_vacio',
            'chk_productos_precio',
            'chk_users_supervisor',
        ], $nombres);
    }

    public function test_ca1_existen_los_indices_unicos(): void
    {
        $unicos = collect(DB::select(
            "SELECT TABLE_NAME AS tabla, INDEX_NAME AS indice,
                    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
              GROUP BY TABLE_NAME, INDEX_NAME"
        ))->map(fn ($f) => "{$f->tabla}({$f->columnas})")->all();

        foreach ([
            'productos(nombre)',
            'clientes(nit)',
            'clientes(telefono)',
            'oportunidad_productos(oportunidad_id,producto_id)',
            'historial_etapas(oportunidad_id,etapa_nueva)',
        ] as $esperado) {
            $this->assertContains($esperado, $unicos);
        }
    }

    public function test_ca1_existen_las_llaves_foraneas(): void
    {
        $fks = collect(DB::select(
            'SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna, REFERENCED_TABLE_NAME AS destino
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
        ))->map(fn ($f) => "{$f->tabla}.{$f->columna}->{$f->destino}")->all();

        foreach ([
            'users.supervisor_id->users',
            'clientes.vendedor_id->users',
            'oportunidades.cliente_id->clientes',
            'oportunidades.vendedor_id->users',
            'oportunidad_productos.oportunidad_id->oportunidades',
            'oportunidad_productos.producto_id->productos',
            'historial_etapas.oportunidad_id->oportunidades',
            'historial_etapas.usuario_id->users',
            'actividades.cliente_id->clientes',
            'actividades.vendedor_id->users',
        ] as $esperada) {
            $this->assertContains($esperada, $fks);
        }
    }

    // ---------------------------------------------------------------
    // CA3 · Las CHECK rechazan (error 3819)
    // ---------------------------------------------------------------

    public function test_ca3_precio_cero(): void
    {
        $this->assertCheckFalla('chk_productos_precio', fn () => DB::table('productos')->insert([
            'nombre' => 'Producto de prueba', 'precio' => 0,
        ]));
    }

    public function test_ca3_telefono_invalido(): void
    {
        $this->assertCheckFalla('chk_clientes_telefono', fn () => DB::table('clientes')->insert([
            'nombre' => 'Paciente de prueba', 'telefono' => '12345678',
        ]));
    }

    public function test_ca3_nit_con_cero_inicial(): void
    {
        $this->assertCheckFalla('chk_clientes_nit', fn () => DB::table('clientes')->insert([
            'nombre' => 'Paciente de prueba', 'telefono' => '55550000', 'nit' => '0123',
        ]));
    }

    public function test_ca3_cantidad_mil(): void
    {
        $this->assertCheckFalla('chk_lineas_cantidad', fn () => DB::table('oportunidad_productos')->insert([
            'oportunidad_id' => $this->oportunidadSinLineas(),
            'producto_id' => DB::table('productos')->where('nombre', 'Plan básico')->value('id'),
            'cantidad' => 1000,
            'precio_unitario' => '1200.00',
        ]));
    }

    public function test_ca3_perdida_sin_motivo(): void
    {
        $this->assertCheckFalla('chk_oportunidades_motivo', fn () => DB::table('oportunidades')->insert(
            $this->oportunidadBase(['etapa' => 'perdida', 'motivo_perdida' => null, 'fecha_cierre' => '2026-10-20 18:00:00'])
        ));
    }

    public function test_ca3_motivo_en_blanco(): void
    {
        $this->assertCheckFalla('chk_oportunidades_motivo_vacio', fn () => DB::table('oportunidades')->insert(
            $this->oportunidadBase(['etapa' => 'perdida', 'motivo_perdida' => '   ', 'fecha_cierre' => '2026-10-20 18:00:00'])
        ));
    }

    public function test_ca3_ganada_sin_fecha_de_cierre(): void
    {
        $this->assertCheckFalla('chk_oportunidades_cierre', fn () => DB::table('oportunidades')->insert(
            $this->oportunidadBase(['etapa' => 'ganada', 'fecha_cierre' => null])
        ));
    }

    public function test_ca3_vendedor_sin_supervisor(): void
    {
        $this->assertCheckFalla('chk_users_supervisor', fn () => DB::table('users')->insert([
            'name' => 'Asesor de prueba',
            'email' => 'asesor.prueba@crm.test',
            'password' => 'x',
            'rol' => 'vendedor',
            'supervisor_id' => null,
        ]));
    }

    // ---------------------------------------------------------------
    // CA5 · Semilla exacta
    // ---------------------------------------------------------------

    public function test_ca5_conteos_de_la_semilla(): void
    {
        $esperados = [
            'users' => 7,
            'productos' => 5,
            'clientes' => 21,
            'oportunidades' => 11,
            'oportunidad_productos' => 13,
            'historial_etapas' => 43,
            'actividades' => 4,
        ];

        foreach ($esperados as $tabla => $n) {
            $this->assertSame($n, DB::table($tabla)->count(), "Conteo de {$tabla}");
        }
    }

    public function test_ca5_cartera_por_vendedor(): void
    {
        $cartera = fn (?string $correo) => DB::table('clientes')
            ->where('vendedor_id', $correo === null ? null : $this->usuario($correo))
            ->count();

        $this->assertSame(3, $cartera('valeria@crm.test'));
        $this->assertSame(2, $cartera('diego@crm.test'));
        $this->assertSame(9, $cartera('carlos@crm.test'));
        $this->assertSame(1, $cartera('ivan@crm.test'));
        $this->assertSame(6, $cartera(null));
    }

    // ---------------------------------------------------------------
    // CA6 · Cifras de control
    // ---------------------------------------------------------------

    public function test_ca6_ventas_de_valeria_por_mes(): void
    {
        $valeria = $this->usuario('valeria@crm.test');

        $this->assertEquals(['ventas' => 2, 'total' => '9250.00'], $this->ventas('2026-10', $valeria));
        $this->assertEquals(['ventas' => 1, 'total' => '3600.00'], $this->ventas('2026-11', $valeria));
    }

    public function test_ca6_pronostico_y_cartera_de_valeria(): void
    {
        $valeria = $this->usuario('valeria@crm.test');

        $this->assertSame('2512.88', $this->pronostico($valeria));
        $this->assertSame(3, DB::table('clientes')->where('vendedor_id', $valeria)->count());
    }

    public function test_ca6_toda_la_empresa_en_octubre(): void
    {
        $this->assertEquals(['ventas' => 4, 'total' => '22450.00'], $this->ventas('2026-10'));
        $this->assertSame('6751.13', $this->pronostico());
    }

    public function test_ca6_ventas_por_producto_en_octubre(): void
    {
        $filas = DB::select(
            "SELECT p.nombre, SUM(l.cantidad) AS unidades, SUM(l.cantidad * l.precio_unitario) AS total
               FROM oportunidades o
               JOIN oportunidad_productos l ON l.oportunidad_id = o.id
               JOIN productos p ON p.id = l.producto_id
              WHERE o.etapa = 'ganada'
                AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?
              GROUP BY p.nombre",
            ['2026-10']
        );

        $porProducto = collect($filas)->mapWithKeys(fn ($f) => [$f->nombre => [(int) $f->unidades, $f->total]])->all();

        $this->assertEquals([
            'Plan básico' => [5, '6000.00'],
            'Plan avanzado' => [2, '5200.00'],
            'Instalación' => [1, '450.00'],
            'Soporte anual' => [3, '10800.00'],
        ], $porProducto);
    }

    public function test_ca6_historial_coincide_con_la_tabla_de_control(): void
    {
        $esperado = [
            'Renovación de planes' => [5, '2026-09-23 16:00:00', '2026-10-05 16:00:00'],
            'Equipo para sucursal' => [5, '2026-10-20 03:30:00', '2026-11-01 03:30:00'],
            'Soporte del hogar' => [5, '2026-10-20 06:30:00', '2026-11-01 06:30:00'],
            'Ampliación a bodega' => [4, '2026-09-23 16:00:00', '2026-10-02 16:00:00'],
            'Capacitación de personal' => [3, '2026-09-26 16:00:00', '2026-10-02 16:00:00'],
            'Plan familiar' => [4, '2026-10-11 18:00:00', '2026-10-20 18:00:00'],
            'Seguimiento anual' => [1, '2026-10-02 16:00:00', '2026-10-02 16:00:00'],
            'Licencias de caja' => [5, '2026-09-28 15:00:00', '2026-10-10 15:00:00'],
            'Mantenimiento' => [2, '2026-09-29 16:00:00', '2026-10-02 16:00:00'],
            'Plan anual' => [5, '2026-10-03 20:00:00', '2026-10-15 20:00:00'],
            'Cotización de equipo' => [4, '2026-09-23 16:00:00', '2026-10-02 16:00:00'],
        ];

        $filas = DB::select(
            'SELECT o.titulo, COUNT(*) AS n, MIN(h.created_at) AS primero, MAX(h.created_at) AS ultimo
               FROM historial_etapas h JOIN oportunidades o ON o.id = h.oportunidad_id
              GROUP BY o.id, o.titulo'
        );

        $real = collect($filas)->mapWithKeys(fn ($f) => [$f->titulo => [(int) $f->n, $f->primero, $f->ultimo]])->all();

        $this->assertEquals($esperado, $real);
    }

    // ---------------------------------------------------------------
    // CA8 · Contraseñas
    // ---------------------------------------------------------------

    public function test_ca8_todas_las_cuentas_tienen_hash_bcrypt_valido(): void
    {
        foreach (DB::table('users')->pluck('password', 'email') as $correo => $hash) {
            $this->assertStringStartsWith('$2y$', $hash, "Hash de {$correo}");
            $this->assertTrue(Hash::check(VerificacionSeeder::CONTRASENA, $hash), "Contraseña de {$correo}");
        }
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    private function assertCheckFalla(string $restriccion, callable $insertar): void
    {
        try {
            $insertar();
        } catch (QueryException $e) {
            $this->assertSame(3819, $e->errorInfo[1], $e->getMessage());
            $this->assertStringContainsString($restriccion, $e->getMessage());

            return;
        }

        $this->fail("La CHECK {$restriccion} no rechazó el INSERT.");
    }

    private function usuario(string $correo): int
    {
        return DB::table('users')->where('email', $correo)->value('id');
    }

    /** Fila válida de oportunidad; cada prueba cambia lo que quiere romper. */
    private function oportunidadBase(array $cambios): array
    {
        return array_merge([
            'cliente_id' => DB::table('clientes')->where('nombre', 'María Coyoy')->value('id'),
            'vendedor_id' => $this->usuario('valeria@crm.test'),
            'titulo' => 'Oportunidad de prueba',
            'etapa' => 'prospecto',
            'motivo_perdida' => null,
            'fecha_cierre' => null,
        ], $cambios);
    }

    private function oportunidadSinLineas(): int
    {
        return DB::table('oportunidades')->where('titulo', 'Seguimiento anual')->value('id');
    }

    /** @return array{ventas: int, total: string} */
    private function ventas(string $mes, ?int $vendedorId = null): array
    {
        $sql = "SELECT COUNT(DISTINCT o.id) AS ventas, COALESCE(SUM(l.cantidad * l.precio_unitario), 0) AS total
                  FROM oportunidades o
                  LEFT JOIN oportunidad_productos l ON l.oportunidad_id = o.id
                 WHERE o.etapa = 'ganada'
                   AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?";
        $parametros = [$mes];

        if ($vendedorId !== null) {
            $sql .= ' AND o.vendedor_id = ?';
            $parametros[] = $vendedorId;
        }

        $fila = DB::selectOne($sql, $parametros);

        return ['ventas' => (int) $fila->ventas, 'total' => (string) $fila->total];
    }

    private function pronostico(?int $vendedorId = null): string
    {
        $sql = "SELECT ROUND(SUM(l.cantidad * l.precio_unitario *
                       CASE o.etapa
                           WHEN 'prospecto' THEN 0.10
                           WHEN 'calificado' THEN 0.25
                           WHEN 'propuesta' THEN 0.50
                           WHEN 'negociacion' THEN 0.75
                       END), 2) AS pronostico
                  FROM oportunidades o
                  JOIN oportunidad_productos l ON l.oportunidad_id = o.id
                 WHERE o.etapa IN ('prospecto', 'calificado', 'propuesta', 'negociacion')";
        $parametros = [];

        if ($vendedorId !== null) {
            $sql .= ' AND o.vendedor_id = ?';
            $parametros[] = $vendedorId;
        }

        return (string) DB::selectOne($sql, $parametros)->pronostico;
    }
}
