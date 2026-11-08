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
}
