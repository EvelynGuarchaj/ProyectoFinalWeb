<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Spec 03 · Menú por rol y entrada al panel: CA18 (menú) y CA19.
 */
class MenuTest extends TestCase
{
    use RefreshDatabase;

    // productos.index (spec 04), clientes.index (spec 05), equipo.index (spec 09),
    // oportunidades.index (spec 12) y reportes.index (spec 14) ya existen; aquí
    // solo se simularían las que faltaran.
    private const RESERVADAS = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
    }

    private function u(string $correo): User
    {
        return User::where('email', $correo)->firstOrFail();
    }

    /** Simula que los specs futuros ya crearon sus rutas con el nombre reservado. */
    private function crearRutasReservadas(): void
    {
        foreach (self::RESERVADAS as $nombre => $url) {
            Route::middleware(['web', 'auth', 'panel'])->get($url, fn () => view('dashboard'))->name($nombre);
        }
        Route::getRoutes()->refreshNameLookups();
    }

    // Desde el spec 05, /dashboard redirige a Pacientes, así que el menú se mira en /profile.
    public function test_ca19_alicia_ve_cuentas_tratamientos_y_pacientes(): void
    {
        $this->actingAs($this->u('admin@crm.test'))->get('/profile')
            ->assertOk()
            ->assertSee('href="'.route('cuentas.index').'"', false)
            ->assertSee('Cuentas')
            ->assertSee('Perfil')
            ->assertSee('Cerrar sesión')
            ->assertSee('Tratamientos')
            ->assertSee('Pacientes')
            ->assertSee('href="'.route('oportunidades.index').'"', false)
            ->assertSee('Planes de tratamiento')
            // Reporte del mes (spec 14).
            ->assertSee('href="'.route('reportes.index').'"', false)
            ->assertSee('Reporte');
    }

    public function test_ca18_el_coordinador_no_ve_cuentas_ni_tratamientos(): void
    {
        $this->crearRutasReservadas();

        $this->actingAs($this->u('sergio@crm.test'))->get('/profile')
            ->assertOk()
            ->assertDontSee('Cuentas')
            ->assertDontSee('Tratamientos')
            ->assertDontSee(route('cuentas.index'), false);
    }

    public function test_ca19_menu_completo_cuando_existen_las_rutas(): void
    {
        $this->crearRutasReservadas();

        $this->actingAs($this->u('admin@crm.test'))->get('/profile')
            ->assertSeeInOrder(['Cuentas', 'Tratamientos', 'Pacientes', 'Planes de tratamiento', 'Reporte'])
            ->assertDontSee('Mi equipo');

        $this->actingAs($this->u('sergio@crm.test'))->get('/profile')
            ->assertSeeInOrder(['Mi equipo', 'Pacientes', 'Planes de tratamiento', 'Reporte']);
    }

    public function test_ca19_dashboard_lleva_a_pacientes_cuando_existe(): void
    {
        $this->crearRutasReservadas();

        $this->actingAs($this->u('sergio@crm.test'))->get('/dashboard')
            ->assertRedirect(route('clientes.index'));
    }
}
