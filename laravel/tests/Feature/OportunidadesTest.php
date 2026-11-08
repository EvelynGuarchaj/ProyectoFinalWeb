<?php

namespace Tests\Feature;

use App\Models\Oportunidad;
use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 12 · Planes de tratamiento en solo lectura (L6, parte 1; L5 y S5 para planes).
 */
class OportunidadesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
    }

    private function u(string $usuario): User
    {
        return User::where('email', $usuario.'@crm.test')->firstOrFail();
    }

    private function titulosVisiblesPara(string $usuario): array
    {
        return Oportunidad::visiblePara($this->u($usuario))->orderBy('titulo')->pluck('titulo')->all();
    }

    // Paso 1 · Alcance: Alicia ve los 11, Sergio 9 (Valeria y Diego), Sandra 2 (Carlos), un asesor ninguno.
    public function test_visible_para_por_rol(): void
    {
        $this->assertCount(11, $this->titulosVisiblesPara('admin'));

        $sergio = $this->titulosVisiblesPara('sergio');
        $this->assertCount(9, $sergio);
        $this->assertNotContains('Plan anual', $sergio);
        $this->assertNotContains('Cotización de equipo', $sergio);

        $this->assertSame(['Cotización de equipo', 'Plan anual'], $this->titulosVisiblesPara('sandra'));

        $this->assertSame([], $this->titulosVisiblesPara('valeria'));
    }

    // Paso 1 · Total en SQL con el precio guardado; sin líneas da 0.00.
    public function test_con_total_calcula_en_sql(): void
    {
        $totales = Oportunidad::conTotal()->pluck('total', 'titulo');

        $this->assertSame('5650.00', (string) $totales['Renovación de planes']);
        $this->assertSame('3600.00', (string) $totales['Equipo para sucursal']);
        $this->assertSame('2199.99', (string) $totales['Plan familiar']);
        $this->assertSame('0.00', (string) $totales['Seguimiento anual']);
    }

    // Paso 1 · Los dos scopes se combinan y lo ajeno no se encuentra.
    public function test_scopes_combinados_y_ajeno_no_se_encuentra(): void
    {
        $planAnual = Oportunidad::where('titulo', 'Plan anual')->firstOrFail();

        $this->assertNull(Oportunidad::visiblePara($this->u('sergio'))->conTotal()->find($planAnual->id));
        $this->assertSame(
            '7200.00',
            (string) Oportunidad::visiblePara($this->u('sandra'))->conTotal()->findOrFail($planAnual->id)->total
        );
    }

    private function plan(string $titulo): Oportunidad
    {
        return Oportunidad::where('titulo', $titulo)->firstOrFail();
    }

    // CA1 · Alicia ve los 11 planes y los filtros se combinan.
    public function test_ca1_administrador_ve_todo_y_filtra(): void
    {
        $alicia = $this->actingAs($this->u('admin'));
        $diego = $this->u('diego');

        $alicia->get('/oportunidades')->assertOk()->assertSee('11 planes')
            ->assertSee('Plan anual')->assertSee('Renovación de planes');

        $alicia->get('/oportunidades?etapa=ganada')->assertOk()->assertSee('5 planes')
            ->assertSee('Renovación de planes')->assertDontSee('Seguimiento anual');

        $alicia->get('/oportunidades?asesor='.$diego->id)->assertOk()->assertSee('2 planes')
            ->assertSee('Licencias de caja')->assertSee('Mantenimiento')->assertDontSee('Renovación de planes');

        $alicia->get('/oportunidades?etapa=ganada&asesor='.$diego->id)->assertOk()->assertSee('1 plan')
            ->assertSee('Licencias de caja')->assertDontSee('Mantenimiento');
    }

    // CA2 · Sergio ve su equipo (9) y Sandra el suyo (2); el filtro de asesor es del equipo.
    public function test_ca2_coordinador_ve_su_equipo(): void
    {
        $this->actingAs($this->u('sergio'))->get('/oportunidades')
            ->assertOk()->assertSee('9 planes')
            ->assertDontSee('Plan anual')->assertDontSee('Cotización de equipo')
            ->assertSee('Diego Castillo')->assertSee('Valeria López')
            ->assertDontSee('Carlos Ajú')->assertDontSee('Iván Toj');

        $this->actingAs($this->u('sandra'))->get('/oportunidades')
            ->assertOk()->assertSee('2 planes')
            ->assertSee('Plan anual')->assertSee('Cotización de equipo')
            ->assertDontSee('Renovación de planes');
    }

    // CA3 · Total y fecha de cierre en hora de Guatemala; abierto sin líneas: Q0.00 y "—".
    public function test_ca3_total_y_fecha_de_cierre(): void
    {
        $this->actingAs($this->u('admin'))->get('/oportunidades')
            ->assertOk()
            ->assertSeeInOrder(['Ferretería Xela', 'Equipo para sucursal', 'Valeria López', 'Aceptado', 'Q3,600.00', '31 oct 2026, 21:30'])
            ->assertSeeInOrder(['Seguimiento anual', 'Valeria López', 'Interesado', 'Q0.00', '—']);
    }

    // CA8 · Filtros no válidos se ignoran sin aviso; un asesor de otro equipo da lista vacía.
    public function test_ca8_filtros_no_validos(): void
    {
        foreach (['?etapa=xyz', '?asesor=abc', '?etapa[]=ganada', '?asesor[]=1'] as $consulta) {
            $this->actingAs($this->u('admin'))->get('/oportunidades'.$consulta)
                ->assertOk()->assertSee('11 planes');
        }

        $this->actingAs($this->u('sergio'))->get('/oportunidades?asesor='.$this->u('carlos')->id)
            ->assertOk()
            ->assertSee('No hay planes de tratamiento con esos filtros.');
    }

    // CA9 (parte de la lista) · Sin botones de crear, editar ni borrar.
    public function test_ca9_lista_sin_acciones_de_escritura(): void
    {
        $this->actingAs($this->u('admin'))->get('/oportunidades')
            ->assertOk()
            ->assertDontSee('Nuevo plan')
            ->assertDontSee('Editar')
            ->assertDontSee('Eliminar')
            ->assertDontSee('name="_method"', false);
    }

    // CA10 · Menú y acceso: sin sesión redirige al login; un asesor no entra al panel.
    public function test_ca10_menu_y_acceso(): void
    {
        $this->actingAs($this->u('sergio'))->get('/profile')
            ->assertSee('href="'.route('oportunidades.index').'"', false)
            ->assertSee('Planes de tratamiento');

        auth()->logout();
        $this->get('/oportunidades')->assertRedirect(route('login'));
    }

    // CA4 · Detalle con líneas, subtotales, total e historial en orden.
    public function test_ca4_detalle_con_lineas_e_historial(): void
    {
        $plan = $this->plan('Renovación de planes');

        $respuesta = $this->actingAs($this->u('admin'))->get('/oportunidades/'.$plan->id)
            ->assertOk()
            ->assertSee('Comercial El Quetzal')
            ->assertSeeInOrder(['Plan avanzado', '2', 'Q2,600.00', 'Q5,200.00'])
            ->assertSeeInOrder(['Instalación', '1', 'Q450.00', 'Q450.00'])
            ->assertSeeInOrder(['Total', 'Q5,650.00'])
            ->assertSeeInOrder(['Historial de etapas', '—', 'Interesado', 'Valeria López', 'En negociación', 'Aceptado', 'Valeria López']);

        $this->assertSame(5, substr_count($respuesta->getContent(), 'Valeria López</td>'));
    }

    // CA5 · El motivo del rechazo solo aparece en un plan perdido.
    public function test_ca5_motivo_del_rechazo(): void
    {
        $alicia = $this->actingAs($this->u('admin'));

        $alicia->get('/oportunidades/'.$this->plan('Plan familiar')->id)
            ->assertOk()
            ->assertSee('Rechazado')
            ->assertSeeInOrder(['Motivo del rechazo', 'Eligió a la competencia']);

        $alicia->get('/oportunidades/'.$this->plan('Renovación de planes')->id)
            ->assertOk()
            ->assertDontSee('Motivo del rechazo');
    }

    // CA6 · La línea muestra el precio guardado, no el del catálogo.
    public function test_ca6_precio_guardado(): void
    {
        DB::table('productos')->where('nombre', 'Licencia anterior')->update(['precio' => '1500.00']);

        $this->actingAs($this->u('admin'))->get('/oportunidades/'.$this->plan('Plan familiar')->id)
            ->assertOk()
            ->assertSeeInOrder(['Licencia anterior', 'Q999.99', 'Q999.99'])
            ->assertSee('Q2,199.99')
            ->assertDontSee('Q1,500.00');
    }

    // CA7 · Lo ajeno, lo inexistente y lo no numérico responden 404.
    public function test_ca7_ajeno_responde_404(): void
    {
        $sergio = $this->actingAs($this->u('sergio'));

        $sergio->get('/oportunidades/'.$this->plan('Plan anual')->id)->assertNotFound();
        $sergio->get('/oportunidades/999999')->assertNotFound();
        $sergio->get('/oportunidades/abc')->assertNotFound();

        $sergio->get('/oportunidades/'.$this->plan('Licencias de caja')->id)->assertOk();
    }

    // CA9 · Solo lectura: PATCH, PUT y DELETE responden 405 y no cambian nada; sin botones.
    public function test_ca9_solo_lectura(): void
    {
        $plan = $this->plan('Seguimiento anual');
        $alicia = $this->actingAs($this->u('admin'));

        $alicia->patch('/oportunidades/'.$plan->id, ['titulo' => 'Cambiado', 'etapa' => 'ganada'])->assertStatus(405);
        $alicia->put('/oportunidades/'.$plan->id, ['titulo' => 'Cambiado'])->assertStatus(405);
        $alicia->delete('/oportunidades/'.$plan->id)->assertStatus(405);

        $this->assertDatabaseHas('oportunidades', ['id' => $plan->id, 'titulo' => 'Seguimiento anual', 'etapa' => 'prospecto']);

        $alicia->get('/oportunidades/'.$plan->id)
            ->assertOk()
            ->assertSee('Este plan todavía no tiene tratamientos.')
            ->assertSeeInOrder(['Total', 'Q0.00'])
            ->assertDontSee('Editar')
            ->assertDontSee('Eliminar')
            ->assertDontSee('name="_method"', false);
    }

    // CA11 · XSS: el título se muestra como texto literal en la lista y el detalle.
    public function test_ca11_xss(): void
    {
        $plan = $this->plan('Seguimiento anual');
        DB::table('oportunidades')->where('id', $plan->id)->update(['titulo' => '<img src=x onerror=alert(1)>']);

        $alicia = $this->actingAs($this->u('admin'));

        $alicia->get('/oportunidades')->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);

        $alicia->get('/oportunidades/'.$plan->id)->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }
}
