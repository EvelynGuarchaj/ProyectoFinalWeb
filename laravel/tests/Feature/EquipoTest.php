<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Spec 09 · Mi equipo (L5, página del coordinador).
 */
class EquipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function u(string $usuario): User
    {
        return User::where('email', $usuario.'@crm.test')->firstOrFail();
    }

    // CA1 (cartera) · Equipo de Sergio: Diego y Valeria, en orden por nombre; ni Carlos ni Iván.
    public function test_ca1_cartera_del_equipo_de_sergio(): void
    {
        $this->actingAs($this->u('sergio'))->get('/equipo?mes=2026-10')
            ->assertOk()
            ->assertSee('Ventas de octubre 2026')
            ->assertSeeInOrder(['Diego Castillo', '2 de 10', 'Valeria López', '3 de 10'])
            ->assertDontSee('Carlos Ajú')
            ->assertDontSee('Iván Toj');
    }

    // CA3 (cartera) · Equipo de Sandra con un asesor desactivado.
    public function test_ca3_cartera_del_equipo_de_sandra_con_desactivado(): void
    {
        $this->actingAs($this->u('sandra'))->get('/equipo?mes=2026-10')
            ->assertOk()
            ->assertSeeInOrder(['Carlos Ajú', '9 de 10', 'Iván Toj', 'Desactivado', '1 de 10'])
            ->assertDontSee('Valeria López')
            ->assertDontSee('Diego Castillo');
    }

    // CA4 · El administrador recibe 403.
    public function test_ca4_el_administrador_recibe_403(): void
    {
        $this->actingAs($this->u('admin'))->get('/equipo')->assertForbidden();
    }

    // CA5 · El mes por omisión es el actual de Guatemala (UTC−6).
    public function test_ca5_mes_por_omision_en_hora_de_guatemala(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 03:30:00', 'UTC'));

        $this->actingAs($this->u('sergio'))->get('/equipo')
            ->assertOk()
            ->assertSee('Ventas de octubre 2026')
            ->assertSee('value="2026-10"', false);

        Carbon::setTestNow(Carbon::parse('2026-11-01 06:30:00', 'UTC'));

        $this->get('/equipo')
            ->assertOk()
            ->assertSee('Ventas de noviembre 2026')
            ->assertSee('value="2026-11"', false);
    }

    // CA6 · Mes no válido: aviso, mes actual y 200.
    public function test_ca6_mes_no_valido_usa_el_mes_actual_con_aviso(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 18:00:00', 'UTC'));
        $sergio = $this->u('sergio');

        foreach (['/equipo?mes=2026-13', '/equipo?mes=hola', '/equipo?mes=2026-1', '/equipo?mes[]=2026-10'] as $url) {
            $this->actingAs($sergio)->get($url)
                ->assertOk()
                ->assertSee('Elija un mes válido (AAAA-MM).')
                ->assertSee('Ventas de noviembre 2026');
        }

        $this->get('/equipo?mes=2026-10')->assertOk()->assertDontSee('Elija un mes válido');
    }

    // CA7 · Menú: "Mi equipo" para el coordinador, no para el administrador.
    public function test_ca7_menu_mi_equipo_solo_para_el_coordinador(): void
    {
        $this->actingAs($this->u('sergio'))->get('/profile')
            ->assertSee('Mi equipo')
            ->assertSee('href="'.route('equipo.index').'"', false);

        $this->actingAs($this->u('admin'))->get('/profile')
            ->assertDontSee('Mi equipo');
    }

    // CA8 · Sin sesión, al inicio de sesión.
    public function test_ca8_sin_sesion_redirige_al_login(): void
    {
        $this->get('/equipo')->assertRedirect(route('login'));
    }
}
