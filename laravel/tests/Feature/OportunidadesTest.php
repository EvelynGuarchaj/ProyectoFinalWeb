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
}
