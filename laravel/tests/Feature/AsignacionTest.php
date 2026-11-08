<?php

namespace Tests\Feature;

use App\Exceptions\CarteraException;
use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Oportunidad;
use App\Models\User;
use App\Services\Cartera;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Spec 07 · Asignación de pacientes (L4 parte 2, sección 3.10, sección 5, S5 y S8 en el panel).
 */
class AsignacionTest extends TestCase
{
    use RefreshDatabase;

    private Cartera $cartera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
        $this->cartera = new Cartera;
    }

    private function u(string $usuario): User
    {
        return User::where('email', $usuario.'@crm.test')->firstOrFail();
    }

    private function paciente(string $nombre): Cliente
    {
        return Cliente::where('nombre', $nombre)->firstOrFail();
    }

    private function plan(string $titulo): Oportunidad
    {
        return Oportunidad::where('titulo', $titulo)->firstOrFail();
    }

    private function cartera(string $usuario): int
    {
        return Cliente::where('vendedor_id', $this->u($usuario)->id)->count();
    }

    /** Pronóstico de las abiertas del asesor, con la fórmula de CLAUDE.md. */
    private function pronostico(string $usuario): string
    {
        return (string) DB::selectOne(
            'SELECT ROUND(COALESCE(SUM(t.total * CASE o.etapa
                    WHEN \'prospecto\' THEN 0.10 WHEN \'calificado\' THEN 0.25
                    WHEN \'propuesta\' THEN 0.50 WHEN \'negociacion\' THEN 0.75 END), 0), 2) AS p
             FROM oportunidades o
             JOIN (SELECT oportunidad_id, SUM(cantidad * precio_unitario) AS total
                   FROM oportunidad_productos GROUP BY oportunidad_id) t ON t.oportunidad_id = o.id
             WHERE o.vendedor_id = ? AND o.etapa IN (\'prospecto\', \'calificado\', \'propuesta\', \'negociacion\')',
            [$this->u($usuario)->id],
        )->p;
    }

    /** Ventas y total del mes (hora de Guatemala) del asesor. */
    private function ventas(string $usuario, string $mes): array
    {
        $fila = DB::selectOne(
            'SELECT COUNT(DISTINCT o.id) AS n, COALESCE(SUM(l.cantidad * l.precio_unitario), 0) AS total
             FROM oportunidades o
             LEFT JOIN oportunidad_productos l ON l.oportunidad_id = o.id
             WHERE o.vendedor_id = ? AND o.etapa = \'ganada\'
               AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, \'%Y-%m\') = ?',
            [$this->u($usuario)->id, $mes],
        );

        return [(int) $fila->n, (string) $fila->total];
    }

    private function esperarError(string $mensaje, callable $accion): void
    {
        try {
            $accion();
            $this->fail('Se esperaba CarteraException: '.$mensaje);
        } catch (CarteraException $e) {
            $this->assertSame($mensaje, $e->getMessage());
            $this->assertSame('vendedor_id', $e->campo);
        }
    }

    // CA1 · Asignar hasta 10 (servicio)

    public function test_ca1_servicio_asigna_hasta_10_y_rechaza_el_11(): void
    {
        $sandra = $this->u('sandra');
        $carlos = $this->u('carlos');
        $this->assertSame(9, $this->cartera('carlos'));

        $this->cartera->asignar($this->paciente('Agencia Cuatro Caminos'), $carlos, $sandra);
        $this->assertSame($carlos->id, $this->paciente('Agencia Cuatro Caminos')->vendedor_id);
        $this->assertSame(10, $this->cartera('carlos'));

        $this->esperarError(Cartera::CARTERA_LLENA,
            fn () => $this->cartera->asignar($this->paciente('Escuela Futuro'), $carlos, $sandra));
        $this->assertNull($this->paciente('Escuela Futuro')->vendedor_id);
        $this->assertSame(10, $this->cartera('carlos'));
    }

    public function test_ca1_servicio_rechaza_asesor_ajeno_desactivado_o_no_vendedor(): void
    {
        $sergio = $this->u('sergio');
        $escuela = $this->paciente('Escuela Futuro');

        foreach (['carlos', 'ivan', 'sandra'] as $destino) {
            $this->esperarError(Cartera::ASESOR_NO_VALIDO,
                fn () => $this->cartera->asignar($escuela, $this->u($destino), $sergio));
        }
        $this->assertNull($this->paciente('Escuela Futuro')->vendedor_id);
    }

    public function test_ca1_servicio_paciente_ajeno_da_404(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->cartera->asignar($this->paciente('Abarrotes San Juan'), $this->u('valeria'), $this->u('sergio'));
    }

    // CA4 · Reasignar

    public function test_ca4_reasignar_mueve_solo_planes_abiertos(): void
    {
        $diego = $this->u('diego');
        $valeria = $this->u('valeria');
        $quetzal = $this->paciente('Comercial El Quetzal');
        $actividad = Actividad::where('cliente_id', $quetzal->id)
            ->where('descripcion', 'Confirmar fecha de instalación')->firstOrFail();

        $this->cartera->asignar($quetzal, $diego, $this->u('sergio'));

        $this->assertSame($diego->id, $this->paciente('Comercial El Quetzal')->vendedor_id);
        $this->assertSame($diego->id, $this->plan('Ampliación a bodega')->vendedor_id);
        $this->assertSame($diego->id, $this->plan('Seguimiento anual')->vendedor_id);
        $this->assertSame($valeria->id, $this->plan('Renovación de planes')->vendedor_id);

        $actividad->refresh();
        $this->assertSame($quetzal->id, $actividad->cliente_id);
        $this->assertSame($valeria->id, $actividad->vendedor_id);

        $this->assertSame(3, $this->cartera('diego'));
        $this->assertSame([2, '9250.00'], $this->ventas('valeria', '2026-10'));
        $this->assertSame('450.00', $this->pronostico('valeria'));
        $this->assertSame('2175.38', $this->pronostico('diego'));
    }

    // CA5 · Paciente de asesor desactivado

    public function test_ca5_reasignar_paciente_de_ivan_a_carlos(): void
    {
        $carlos = $this->u('carlos');
        $this->cartera->asignar($this->paciente('Hotel Los Pinos'), $carlos, $this->u('sandra'));

        $this->assertSame($carlos->id, $this->paciente('Hotel Los Pinos')->vendedor_id);
    }

    // CA7 · Mismo asesor

    public function test_ca7_asignar_al_mismo_asesor_no_cambia_nada(): void
    {
        $valeria = $this->u('valeria');
        $antes = Oportunidad::orderBy('id')->pluck('vendedor_id', 'id')->all();

        $this->cartera->asignar($this->paciente('Comercial El Quetzal'), $valeria, $this->u('admin'));

        $this->assertSame(3, $this->cartera('valeria'));
        $this->assertSame($antes, Oportunidad::orderBy('id')->pluck('vendedor_id', 'id')->all());
    }

    public function test_ca7_asesor_con_10_reasignado_a_si_mismo_no_da_error_de_cartera(): void
    {
        $sandra = $this->u('sandra');
        $carlos = $this->u('carlos');
        $this->cartera->asignar($this->paciente('Agencia Cuatro Caminos'), $carlos, $sandra);
        $this->assertSame(10, $this->cartera('carlos'));

        $this->cartera->asignar($this->paciente('Abarrotes San Juan'), $carlos, $sandra);

        $this->assertSame(10, $this->cartera('carlos'));
    }

    // CA1 · Asignar hasta 10 desde el panel

    private function asignar(string $actor, string $paciente, mixed $vendedorId, string $desde = '/clientes')
    {
        $datos = $vendedorId === null ? [] : ['vendedor_id' => $vendedorId];

        return $this->actingAs($this->u($actor))->from($desde)
            ->patch('/clientes/'.$this->paciente($paciente)->id.'/asignar', $datos);
    }

    public function test_ca1_sandra_asigna_a_carlos_hasta_10(): void
    {
        $carlos = $this->u('carlos');
        $agencia = $this->paciente('Agencia Cuatro Caminos');

        $this->asignar('sandra', 'Agencia Cuatro Caminos', $carlos->id)
            ->assertRedirect('/clientes')
            ->assertSessionHas('status', 'El paciente se asignó correctamente.');
        $this->assertSame($carlos->id, $agencia->refresh()->vendedor_id);
        $this->assertSame(10, $this->cartera('carlos'));

        $escuela = $this->paciente('Escuela Futuro');
        $this->asignar('sandra', 'Escuela Futuro', $carlos->id)
            ->assertRedirect('/clientes')
            ->assertSessionHasErrors(['vendedor_id' => Cartera::CARTERA_LLENA], null, 'asignar'.$escuela->id);
        $this->assertNull($escuela->refresh()->vendedor_id);
        $this->assertSame(10, $this->cartera('carlos'));
    }

    // CA2 · Asesor ajeno o desactivado

    public function test_ca2_sergio_no_asigna_a_carlos_ivan_ni_vacio(): void
    {
        $escuela = $this->paciente('Escuela Futuro');

        foreach ([$this->u('carlos')->id, $this->u('ivan')->id, null, 'abc'] as $destino) {
            $this->asignar('sergio', 'Escuela Futuro', $destino)
                ->assertRedirect('/clientes')
                ->assertSessionHasErrors(['vendedor_id' => Cartera::ASESOR_NO_VALIDO], null, 'asignar'.$escuela->id);
            $this->assertNull($escuela->refresh()->vendedor_id);
        }
    }

    // CA3 · Paciente ajeno da 404

    public function test_ca3_sergio_no_asigna_paciente_ajeno(): void
    {
        $abarrotes = $this->paciente('Abarrotes San Juan');

        $this->asignar('sergio', 'Abarrotes San Juan', $this->u('valeria')->id)->assertNotFound();
        $this->assertSame($this->u('carlos')->id, $abarrotes->refresh()->vendedor_id);

        $this->actingAs($this->u('sergio'))
            ->patch('/clientes/999999/asignar', ['vendedor_id' => $this->u('valeria')->id])
            ->assertNotFound();
    }

    // CA6 · Selector

    public function test_ca6_selector_de_sergio_y_de_alicia(): void
    {
        $r = $this->actingAs($this->u('sergio'))->get('/clientes')->assertOk();
        $this->assertSame(['Diego Castillo', 'Valeria López'], $r->viewData('asignables')->pluck('name')->all());
        $r->assertSee('Diego Castillo (2 de 10)')
            ->assertSee('Valeria López (3 de 10)')
            ->assertDontSee('Carlos')
            ->assertDontSee('Iván Toj (');

        $r = $this->actingAs($this->u('admin'))->get('/clientes')->assertOk();
        $nombres = $r->viewData('asignables')->pluck('name')->all();
        $this->assertContains('Carlos Ajú', $nombres);
        $this->assertNotContains('Iván Toj', $nombres);
        $r->assertSee('Carlos Ajú (9 de 10)');
    }

    public function test_ca6_asesor_actual_preseleccionado_en_el_detalle(): void
    {
        $valeria = $this->u('valeria');
        $this->actingAs($this->u('sergio'))
            ->get('/clientes/'.$this->paciente('Comercial El Quetzal')->id)
            ->assertOk()
            ->assertSee('<option value="'.$valeria->id.'" selected>', false);
    }

    // CA8 · Administrador sin límite de equipo

    public function test_ca8_alicia_asigna_en_los_dos_equipos(): void
    {
        $this->asignar('admin', 'Café Zunil', $this->u('carlos')->id)->assertSessionHasNoErrors();
        $this->asignar('admin', 'Gimnasio Fuerza', $this->u('valeria')->id)->assertSessionHasNoErrors();

        $this->assertSame($this->u('carlos')->id, $this->paciente('Café Zunil')->vendedor_id);
        $this->assertSame($this->u('valeria')->id, $this->paciente('Gimnasio Fuerza')->vendedor_id);
    }

    // CA9 · El error sale solo en su fila

    public function test_ca9_error_solo_en_la_fila_del_paciente(): void
    {
        $this->asignar('sandra', 'Agencia Cuatro Caminos', $this->u('carlos')->id);
        $this->asignar('sandra', 'Escuela Futuro', $this->u('carlos')->id);

        $html = $this->actingAs($this->u('sandra'))->get('/clientes?asesor=sin')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, Cartera::CARTERA_LLENA));
        $fila = strpos($html, 'Escuela Futuro</a>');
        $error = strpos($html, Cartera::CARTERA_LLENA);
        $siguiente = strpos($html, 'Gimnasio Fuerza</a>');
        $this->assertTrue($fila < $error && $error < $siguiente, 'El error debe salir en la fila de Escuela Futuro.');
    }

    // CA10 · Liberar con plan abierto

    private function liberar(string $actor, string $paciente)
    {
        return $this->actingAs($this->u($actor))->from('/clientes')
            ->patch('/clientes/'.$this->paciente($paciente)->id.'/liberar');
    }

    public function test_ca10_no_libera_con_plan_abierto(): void
    {
        $this->liberar('sergio', 'Ferretería Xela')
            ->assertRedirect('/clientes')
            ->assertSessionHas('error', Cartera::CON_PLANES_ABIERTOS);
        $this->assertSame($this->u('valeria')->id, $this->paciente('Ferretería Xela')->vendedor_id);

        $this->actingAs($this->u('sergio'))->get('/clientes')->assertSee(Cartera::CON_PLANES_ABIERTOS);
    }

    // CA11 · Liberar con planes cerrados

    public function test_ca11_libera_paciente_con_planes_cerrados(): void
    {
        $valeria = $this->u('valeria');

        $this->liberar('sergio', 'María Coyoy')
            ->assertRedirect('/clientes')
            ->assertSessionHas('status', 'El paciente quedó disponible.');

        $this->assertNull($this->paciente('María Coyoy')->vendedor_id);
        $this->assertSame(2, $this->cartera('valeria'));
        $this->assertSame($valeria->id, $this->plan('Soporte del hogar')->vendedor_id);
        $this->assertSame([1, '3600.00'], $this->ventas('valeria', '2026-11'));

        $this->actingAs($this->u('sergio'))->get('/clientes?q=Coyoy')
            ->assertSeeInOrder(['María Coyoy', 'Disponible'])
            ->assertDontSee('Liberar');
    }

    public function test_ca11_liberar_paciente_ajeno_da_404(): void
    {
        $this->liberar('sergio', 'Abarrotes San Juan')->assertNotFound();
        $this->assertSame($this->u('carlos')->id, $this->paciente('Abarrotes San Juan')->vendedor_id);
    }

    // CA12 · Crear con asesor

    private function nuevo(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Paciente Prueba',
            'nit' => 'CF',
            'telefono' => '7761-9999',
            'correo' => '',
            'vendedor_id' => '',
        ], $cambios);
    }

    private function crear(string $actor, array $datos)
    {
        return $this->actingAs($this->u($actor))->from('/clientes/create')->post('/clientes', $datos);
    }

    public function test_ca12_alicia_crea_con_asesor_y_sin_asesor(): void
    {
        $valeria = $this->u('valeria');

        $this->crear('admin', $this->nuevo(['vendedor_id' => $valeria->id]))->assertSessionHasNoErrors();
        $this->assertSame($valeria->id, $this->paciente('Paciente Prueba')->vendedor_id);
        $this->assertSame(4, $this->cartera('valeria'));

        $this->crear('admin', $this->nuevo(['nombre' => 'Otro Prueba', 'telefono' => '7761-9998']))
            ->assertSessionHasNoErrors();
        $this->assertNull($this->paciente('Otro Prueba')->vendedor_id);
    }

    public function test_ca12_crear_con_asesor_lleno_vuelve_con_lo_escrito(): void
    {
        $carlos = $this->u('carlos');
        $this->asignar('sandra', 'Agencia Cuatro Caminos', $carlos->id);
        $this->assertSame(10, $this->cartera('carlos'));
        $total = Cliente::count();

        $this->crear('sandra', $this->nuevo(['vendedor_id' => $carlos->id]))
            ->assertRedirect('/clientes/create')
            ->assertSessionHasErrors(['vendedor_id' => Cartera::CARTERA_LLENA])
            ->assertSessionHasInput('nombre', 'Paciente Prueba')
            ->assertSessionHasInput('telefono', '7761-9999');

        $this->assertSame($total, Cliente::count());
        $this->assertSame(10, $this->cartera('carlos'));
    }

    public function test_ca12_selector_del_alta(): void
    {
        $this->actingAs($this->u('sergio'))->get('/clientes/create')
            ->assertOk()
            ->assertSeeInOrder(['Disponible (sin asesor)', 'Diego Castillo (2 de 10)', 'Valeria López (3 de 10)'])
            ->assertDontSee('Carlos Ajú');
    }

    // CA13 · Crear con asesor ajeno; editar no cambia el asesor

    public function test_ca13_sergio_no_crea_con_carlos_ni_ivan(): void
    {
        $total = Cliente::count();

        foreach (['carlos', 'ivan'] as $destino) {
            $this->crear('sergio', $this->nuevo(['vendedor_id' => $this->u($destino)->id]))
                ->assertSessionHasErrors(['vendedor_id' => Cartera::ASESOR_NO_VALIDO]);
        }

        $this->assertSame($total, Cliente::count());
    }

    public function test_ca13_editar_con_vendedor_id_a_mano_no_cambia_el_asesor(): void
    {
        $zunil = $this->paciente('Café Zunil');

        $this->actingAs($this->u('admin'))->put('/clientes/'.$zunil->id, [
            'nombre' => 'Café Zunil',
            'nit' => $zunil->nitEnPantalla(),
            'telefono' => $zunil->telefono,
            'correo' => $zunil->correo ?? '',
            'vendedor_id' => $this->u('valeria')->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull($zunil->refresh()->vendedor_id);

        $this->actingAs($this->u('admin'))->get('/clientes/'.$zunil->id.'/edit')
            ->assertOk()
            ->assertDontSee('name="vendedor_id"', false);
    }
}
