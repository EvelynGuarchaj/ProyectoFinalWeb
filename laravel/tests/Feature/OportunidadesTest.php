<?php

namespace Tests\Feature;

use App\Models\Oportunidad;
use App\Models\User;
use Database\Seeders\VerificacionSeeder;
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
}
