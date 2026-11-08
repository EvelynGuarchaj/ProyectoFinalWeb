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

    private const RESERVADAS = [
        'productos.index' => '/productos',
        'equipo.index' => '/equipo',
        'clientes.index' => '/clientes',
        'oportunidades.index' => '/oportunidades',
        'reportes.index' => '/reportes',
    ];

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

    public function test_ca19_alicia_ve_cuentas_y_el_saludo(): void
    {
        $this->actingAs($this->u('admin@crm.test'))->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('cuentas.index').'"', false)
            ->assertSee('Cuentas')
            ->assertSee('Bienvenido(a), Alicia Morales')
            ->assertSee('Perfil')
            ->assertSee('Cerrar sesión')
            // Rutas que todavía no existen: sin enlace.
            ->assertDontSee('Tratamientos')
            ->assertDontSee('Pacientes');
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
