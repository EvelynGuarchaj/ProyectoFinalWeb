<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Spec 04 · Catálogo de tratamientos (L3 y R-P9).
 */
class ProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    private User $sergio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
        $this->alicia = User::where('email', 'admin@crm.test')->firstOrFail();
        $this->sergio = User::where('email', 'sergio@crm.test')->firstOrFail();
    }

    public function test_lista_ordenada_con_los_desactivados(): void
    {
        $this->actingAs($this->alicia)->get('/productos')
            ->assertOk()
            ->assertSee('Tratamientos')
            ->assertSeeInOrder([
                'Instalación', 'Q450.00', 'No', 'Activo',
                'Licencia anterior', 'Q999.99', 'No', 'Desactivado',
                'Plan avanzado', 'Q2,750.50',
                'Plan básico', 'Q1,200.00',
                'Soporte anual', 'Q3,600.00',
            ]);
    }

    public function test_menu_del_administrador_muestra_tratamientos(): void
    {
        $this->actingAs($this->alicia)->get('/profile')
            ->assertSee('href="'.route('productos.index').'"', false)
            ->assertSeeInOrder(['Cuentas', 'Tratamientos']);
    }

    public function test_ca10_el_coordinador_recibe_403_en_la_lista(): void
    {
        $this->actingAs($this->sergio)->get('/productos')->assertForbidden();

        $this->actingAs($this->sergio)->get('/profile')
            ->assertDontSee('Tratamientos');
    }

    public function test_ca10_el_coordinador_no_crea_tratamientos(): void
    {
        $this->actingAs($this->sergio)->get('/productos/create')->assertForbidden();
        $this->actingAs($this->sergio)->post('/productos', $this->nuevo())->assertForbidden();

        $this->assertDatabaseMissing('productos', ['nombre' => 'Sellante dental']);
    }

    /** Datos válidos de un tratamiento nuevo, como los manda el formulario. */
    private function nuevo(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Sellante dental',
            'precio' => '150',
            'activo' => '1',
            'requiere_evaluacion' => '0',
        ], $cambios);
    }

    public function test_el_formulario_de_alta_trae_activo_marcado(): void
    {
        $this->actingAs($this->alicia)->get('/productos/create')
            ->assertOk()
            ->assertSee('Nuevo tratamiento')
            ->assertSee('name="activo" type="checkbox" value="1" checked', false)
            ->assertDontSee('name="requiere_evaluacion" type="checkbox" value="1" checked', false);
    }

    public function test_ca1_alta_de_sellante_dental(): void
    {
        $this->actingAs($this->alicia)->post('/productos', $this->nuevo())
            ->assertRedirect(route('productos.index'))
            ->assertSessionHas('status', 'El tratamiento se creó correctamente.');

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Sellante dental',
            'precio' => '150.00',
            'activo' => 1,
            'requiere_evaluacion' => 0,
        ]);

        $this->actingAs($this->alicia)->get('/productos')
            ->assertSeeInOrder(['Sellante dental', 'Q150.00', 'No', 'Activo']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function preciosInvalidos(): array
    {
        return [
            'cero' => ['0', 'El precio debe ser mayor que cero.'],
            'negativo' => ['-5', 'El precio debe ser mayor que cero.'],
            'tres decimales' => ['12.345', 'El precio puede tener como máximo dos decimales.'],
            'texto' => ['abc', 'Escriba el precio como un número, por ejemplo 150.00.'],
            'vacío' => ['', 'Escriba el precio.'],
            'demasiado grande' => ['100000000', 'El precio no puede pasar de Q99,999,999.99.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('preciosInvalidos')]
    public function test_ca2_precio_invalido(string $precio, string $mensaje): void
    {
        $antes = DB::table('productos')->count();

        $this->actingAs($this->alicia)
            ->from('/productos/create')
            ->post('/productos', $this->nuevo(['precio' => $precio]))
            ->assertRedirect('/productos/create')
            ->assertSessionHasErrors(['precio' => $mensaje])
            ->assertSessionHasInput('nombre', 'Sellante dental');

        // Un solo mensaje junto al precio.
        $this->assertCount(1, session('errors')->get('precio'));
        $this->assertSame($antes, DB::table('productos')->count());
    }

    public function test_ca2_lo_escrito_vuelve_al_formulario(): void
    {
        $this->actingAs($this->alicia)
            ->from('/productos/create')
            ->post('/productos', $this->nuevo(['precio' => '12.345']));

        $this->actingAs($this->alicia)->get('/productos/create')
            ->assertSee('value="Sellante dental"', false)
            ->assertSee('value="12.345"', false)
            ->assertSee('El precio puede tener como máximo dos decimales.');
    }

    public function test_ca3_nombre_repetido(): void
    {
        $antes = DB::table('productos')->count();

        $this->actingAs($this->alicia)
            ->from('/productos/create')
            ->post('/productos', $this->nuevo(['nombre' => 'Plan básico']))
            ->assertSessionHasErrors(['nombre' => 'Ya existe un tratamiento con ese nombre.']);

        $this->assertSame($antes, DB::table('productos')->count());
    }

    public function test_ca4_nombre_vacio_o_largo(): void
    {
        $this->actingAs($this->alicia)
            ->post('/productos', $this->nuevo(['nombre' => '']))
            ->assertSessionHasErrors(['nombre' => 'Escriba el nombre del tratamiento.']);

        $this->actingAs($this->alicia)
            ->post('/productos', $this->nuevo(['nombre' => str_repeat('a', 121)]))
            ->assertSessionHasErrors(['nombre' => 'El nombre no puede pasar de 120 caracteres.']);

        $this->assertDatabaseMissing('productos', ['nombre' => str_repeat('a', 121)]);
    }

    public function test_casillas_sin_valor_no_se_aceptan(): void
    {
        $datos = $this->nuevo();
        unset($datos['activo'], $datos['requiere_evaluacion']);

        $this->actingAs($this->alicia)->post('/productos', $datos)
            ->assertSessionHasErrors([
                'activo' => 'Indique si el tratamiento está activo.',
                'requiere_evaluacion' => 'Indique si el tratamiento requiere evaluación.',
            ]);
    }

    public function test_ca12_el_nombre_se_muestra_como_texto(): void
    {
        $this->actingAs($this->alicia)->post('/productos', $this->nuevo(['nombre' => '<b>x</b>']));

        $this->actingAs($this->alicia)->get('/productos')
            ->assertSee('&lt;b&gt;x&lt;/b&gt;', false)
            ->assertDontSee('<b>x</b>', false);
    }

    private function producto(string $nombre): Producto
    {
        return Producto::where('nombre', $nombre)->firstOrFail();
    }

    /** Datos del formulario de edición tal como vienen de la base. */
    private function datosDe(Producto $producto, array $cambios = []): array
    {
        return array_merge([
            'nombre' => $producto->nombre,
            'precio' => $producto->precio,
            'activo' => $producto->activo ? '1' : '0',
            'requiere_evaluacion' => $producto->requiere_evaluacion ? '1' : '0',
        ], $cambios);
    }

    /** Líneas de un plan: [[tratamiento, cantidad, precio_unitario], ...]. */
    private function lineasDe(string $titulo): array
    {
        return array_map(fn ($fila) => [$fila->nombre, (int) $fila->cantidad, $fila->precio_unitario], DB::select(
            'SELECT p.nombre, l.cantidad, l.precio_unitario
               FROM oportunidad_productos l
               JOIN oportunidades o ON o.id = l.oportunidad_id
               JOIN productos p ON p.id = l.producto_id
              WHERE o.titulo = ?
              ORDER BY p.nombre',
            [$titulo]
        ));
    }

    /** Ventas y total de octubre 2026 (sección 11), de todos o de un asesor. */
    private function ventasOctubre(?int $vendedorId = null): array
    {
        $sql = "SELECT COUNT(DISTINCT o.id) AS ventas, COALESCE(SUM(l.cantidad * l.precio_unitario), 0) AS total
                  FROM oportunidades o
                  LEFT JOIN oportunidad_productos l ON l.oportunidad_id = o.id
                 WHERE o.etapa = 'ganada'
                   AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = '2026-10'";
        $parametros = [];
        if ($vendedorId !== null) {
            $sql .= ' AND o.vendedor_id = ?';
            $parametros[] = $vendedorId;
        }
        $fila = DB::selectOne($sql, $parametros);

        return [(int) $fila->ventas, (string) $fila->total];
    }

    /** Pronóstico de las abiertas, sin filtro de mes (sección 11). */
    private function pronostico(?int $vendedorId = null): string
    {
        $sql = "SELECT ROUND(SUM(l.cantidad * l.precio_unitario *
                       CASE o.etapa WHEN 'prospecto' THEN 0.10 WHEN 'calificado' THEN 0.25
                                    WHEN 'propuesta' THEN 0.50 WHEN 'negociacion' THEN 0.75 END), 2) AS p
                  FROM oportunidades o
                  JOIN oportunidad_productos l ON l.oportunidad_id = o.id
                 WHERE o.etapa IN ('prospecto', 'calificado', 'propuesta', 'negociacion')";
        $parametros = [];
        if ($vendedorId !== null) {
            $sql .= ' AND o.vendedor_id = ?';
            $parametros[] = $vendedorId;
        }

        return (string) DB::selectOne($sql, $parametros)->p;
    }

    public function test_el_formulario_de_edicion_trae_los_datos(): void
    {
        $plan = $this->producto('Plan avanzado');

        $this->actingAs($this->alicia)->get(route('productos.edit', $plan))
            ->assertOk()
            ->assertSee('Editar tratamiento')
            ->assertSee('value="Plan avanzado"', false)
            // El precio tal como está en la base, sin Q ni comas.
            ->assertSee('value="2750.50"', false);
    }

    public function test_ca5_editar_sin_cambiar_el_nombre(): void
    {
        $plan = $this->producto('Plan básico');

        $this->actingAs($this->alicia)
            ->put(route('productos.update', $plan), $this->datosDe($plan, ['precio' => '1300']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('productos.index'))
            ->assertSessionHas('status', 'El tratamiento se actualizó correctamente.');

        $this->assertSame('1300.00', $plan->fresh()->precio);
    }

    public function test_editar_con_el_nombre_de_otro_tratamiento(): void
    {
        $plan = $this->producto('Plan básico');

        $this->actingAs($this->alicia)
            ->put(route('productos.update', $plan), $this->datosDe($plan, ['nombre' => 'Plan avanzado']))
            ->assertSessionHasErrors(['nombre' => 'Ya existe un tratamiento con ese nombre.']);

        $this->assertSame('Plan básico', $plan->fresh()->nombre);
    }

    public function test_ca6_cambiar_un_precio_no_cambia_el_pasado(): void
    {
        $valeria = User::where('email', 'valeria@crm.test')->firstOrFail()->id;
        $antes = [$this->ventasOctubre($valeria), $this->ventasOctubre(), $this->pronostico($valeria), $this->pronostico()];
        $this->assertSame([[2, '9250.00'], [4, '22450.00'], '2512.88', '6751.13'], $antes);

        $plan = $this->producto('Plan avanzado');
        $this->actingAs($this->alicia)
            ->put(route('productos.update', $plan), $this->datosDe($plan, ['precio' => '3000.00']))
            ->assertSessionHasNoErrors();

        $this->assertSame('3000.00', $plan->fresh()->precio);
        $this->actingAs($this->alicia)->get('/productos')->assertSeeInOrder(['Plan avanzado', 'Q3,000.00']);

        $this->assertSame(
            [['Instalación', 1, '450.00'], ['Plan avanzado', 2, '2600.00']],
            $this->lineasDe('Renovación de planes')
        );
        $this->assertSame('5650.00', (string) DB::selectOne(
            "SELECT SUM(l.cantidad * l.precio_unitario) AS total
               FROM oportunidad_productos l JOIN oportunidades o ON o.id = l.oportunidad_id
              WHERE o.titulo = 'Renovación de planes'"
        )->total);

        $despues = [$this->ventasOctubre($valeria), $this->ventasOctubre(), $this->pronostico($valeria), $this->pronostico()];
        $this->assertSame($antes, $despues);
    }

    public function test_ca9_marcar_y_desmarcar_requiere_evaluacion(): void
    {
        $plan = $this->producto('Plan básico');

        $this->actingAs($this->alicia)
            ->put(route('productos.update', $plan), $this->datosDe($plan, ['requiere_evaluacion' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', ['id' => $plan->id, 'requiere_evaluacion' => 1]);
        $this->actingAs($this->alicia)->get('/productos')->assertSeeInOrder(['Plan básico', 'Q1,200.00', 'Sí']);
        $this->actingAs($this->alicia)->get(route('productos.edit', $plan))
            ->assertSee('name="requiere_evaluacion" type="checkbox" value="1" checked', false);

        $this->actingAs($this->alicia)
            ->put(route('productos.update', $plan), $this->datosDe($plan->fresh(), ['requiere_evaluacion' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', ['id' => $plan->id, 'requiere_evaluacion' => 0]);
    }

    public function test_ca10_el_coordinador_no_edita_tratamientos(): void
    {
        $plan = $this->producto('Plan básico');

        $this->actingAs($this->sergio)->get(route('productos.edit', $plan))->assertForbidden();
        $this->actingAs($this->sergio)
            ->put(route('productos.update', $plan), $this->datosDe($plan, ['precio' => '1', 'requiere_evaluacion' => '1']))
            ->assertForbidden();

        $fresco = $plan->fresh();
        $this->assertSame('1200.00', $fresco->precio);
        $this->assertFalse($fresco->requiere_evaluacion);
    }

    public function test_carrera_1062_se_muestra_junto_al_nombre(): void
    {
        // Simula que otra escritura guarda el mismo nombre justo después de la regla unique.
        Producto::creating(function (Producto $producto) {
            DB::table('productos')->insert([
                'nombre' => $producto->nombre, 'precio' => '1.00', 'activo' => true,
                'requiere_evaluacion' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->actingAs($this->alicia)
            ->from('/productos/create')
            ->post('/productos', $this->nuevo())
            ->assertRedirect('/productos/create')
            ->assertSessionHasErrors(['nombre' => 'Ya existe un tratamiento con ese nombre.'])
            ->assertSessionHasInput('precio', '150');

        $this->assertSame(1, DB::table('productos')->where('nombre', 'Sellante dental')->count());
    }

    public function test_carrera_1062_al_editar(): void
    {
        $plan = $this->producto('Plan básico');
        Producto::updating(function (Producto $producto) {
            DB::table('productos')->insert([
                'nombre' => $producto->nombre, 'precio' => '1.00', 'activo' => true,
                'requiere_evaluacion' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->actingAs($this->alicia)
            ->from(route('productos.edit', $plan))
            ->put(route('productos.update', $plan), $this->datosDe($plan, ['nombre' => 'Blanqueamiento']))
            ->assertRedirect(route('productos.edit', $plan))
            ->assertSessionHasErrors(['nombre' => 'Ya existe un tratamiento con ese nombre.']);

        $this->assertSame('Plan básico', $plan->fresh()->nombre);
    }

    public function test_ca7_desactivar_y_activar_instalacion(): void
    {
        $instalacion = $this->producto('Instalación');
        $this->assertSame([['Instalación', 2, '450.00']], $this->lineasDe('Capacitación de personal'));

        $this->actingAs($this->alicia)->get('/productos')
            ->assertSeeInOrder(['Instalación', 'Activo', 'Desactivar']);

        $this->actingAs($this->alicia)
            ->patch(route('productos.estado', $instalacion))
            ->assertRedirect(route('productos.index'))
            ->assertSessionHas('status', 'El tratamiento se desactivó.');

        $this->assertDatabaseHas('productos', ['id' => $instalacion->id, 'activo' => 0]);
        // Lo que usará GET /productos de la API (spec 11).
        $activos = array_column(DB::select('SELECT nombre FROM productos WHERE activo = 1 ORDER BY nombre'), 'nombre');
        $this->assertSame(['Plan avanzado', 'Plan básico', 'Soporte anual'], $activos);
        // Las líneas que ya lo tienen no cambian.
        $this->assertSame([['Instalación', 2, '450.00']], $this->lineasDe('Capacitación de personal'));

        $this->actingAs($this->alicia)->get('/productos')
            ->assertSeeInOrder(['Instalación', 'Desactivado', 'Activar', 'Licencia anterior']);

        $this->actingAs($this->alicia)
            ->patch(route('productos.estado', $instalacion))
            ->assertSessionHas('status', 'El tratamiento se activó.');

        $this->assertDatabaseHas('productos', ['id' => $instalacion->id, 'activo' => 1]);
    }

    public function test_ca8_no_hay_ruta_ni_boton_para_borrar(): void
    {
        $plan = $this->producto('Plan básico');
        $antes = DB::table('productos')->count();

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('productos.destroy'));
        $this->actingAs($this->alicia)->delete('/productos/'.$plan->id)->assertMethodNotAllowed();
        $this->assertSame($antes, DB::table('productos')->count());

        $this->actingAs($this->alicia)->get('/productos')
            ->assertDontSee('Eliminar')
            ->assertDontSee('Borrar')
            ->assertDontSee('value="DELETE"', false);
    }

    public function test_ca10_el_coordinador_no_cambia_el_estado(): void
    {
        $instalacion = $this->producto('Instalación');

        $this->actingAs($this->sergio)->patch(route('productos.estado', $instalacion))->assertForbidden();

        $this->assertTrue($instalacion->fresh()->activo);
    }
}
