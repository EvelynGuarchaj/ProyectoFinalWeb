<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reporte;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Spec 14 · Reporte del mes (L6, R7 y sección 11).
 *
 * Las cifras se comparan con el texto DECIMAL que devuelve MySQL ('9250.00',
 * '2512.88'), nunca con floats, para no aceptar un cálculo hecho con float.
 */
class ReporteTest extends TestCase
{
    use RefreshDatabase;

    private Reporte $reporte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
        $this->reporte = new Reporte;
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

    /** [ventas, total, pronostico] como texto, para comparar sin floats. */
    private function cifras(object $fila): array
    {
        return [(string) $fila->ventas, (string) $fila->total, (string) $fila->pronostico];
    }

    /** Fila de una colección por nombre (asesor o coordinador). */
    private function fila(Collection $filas, string $nombre): object
    {
        $fila = $filas->firstWhere('name', $nombre);
        $this->assertNotNull($fila, "No aparece la fila de {$nombre}.");

        return $fila;
    }

    // ── Servicio: por asesor ───────────────────────────────────────────────

    // CA1 · Administrador, octubre 2026: cada asesor con cartera, ventas, total y pronóstico (11.1).
    public function test_ca1_por_asesor_del_administrador_en_octubre(): void
    {
        $filas = $this->reporte->porAsesor($this->u('admin'), '2026-10');

        $this->assertSame(['Carlos Ajú', 'Iván Toj', 'Diego Castillo', 'Valeria López'], $filas->pluck('name')->all(),
            'Orden por coordinador (Sandra, Sergio) y luego por asesor.');

        $this->assertSame(['2', '9250.00', '2512.88'], $this->cifras($this->fila($filas, 'Valeria López')));
        $this->assertSame(['1', '6000.00', '112.50'], $this->cifras($this->fila($filas, 'Diego Castillo')));
        $this->assertSame(['1', '7200.00', '4125.75'], $this->cifras($this->fila($filas, 'Carlos Ajú')));
        $this->assertSame(['0', '0.00', '0.00'], $this->cifras($this->fila($filas, 'Iván Toj')));

        $this->assertSame(3, (int) $this->fila($filas, 'Valeria López')->cartera);
        $this->assertSame(2, (int) $this->fila($filas, 'Diego Castillo')->cartera);
        $this->assertSame(9, (int) $this->fila($filas, 'Carlos Ajú')->cartera);
        $this->assertSame(1, (int) $this->fila($filas, 'Iván Toj')->cartera);

        $this->assertSame('Sergio Ruiz', $this->fila($filas, 'Valeria López')->coordinador);
        $this->assertSame('Sandra Pérez', $this->fila($filas, 'Iván Toj')->coordinador);
        $this->assertFalse((bool) $this->fila($filas, 'Iván Toj')->activo);
    }

    // CA1 · Por equipo y toda la clínica.
    public function test_ca1_por_equipo_y_toda_la_clinica(): void
    {
        $equipos = $this->reporte->porEquipo('2026-10');

        $this->assertSame(['Sandra Pérez', 'Sergio Ruiz'], $equipos->pluck('name')->all());
        $this->assertSame(['3', '15250.00', '2625.38'], $this->cifras($this->fila($equipos, 'Sergio Ruiz')));
        $this->assertSame(['1', '7200.00', '4125.75'], $this->cifras($this->fila($equipos, 'Sandra Pérez')));

        $this->assertSame(['4', '22450.00', '6751.13'], $this->cifras($this->reporte->totales($this->u('admin'), '2026-10')));
    }

    // CA3 · El pronóstico no depende del mes.
    public function test_ca3_noviembre_cambia_ventas_pero_no_pronostico(): void
    {
        $valeria = $this->fila($this->reporte->porAsesor($this->u('admin'), '2026-11'), 'Valeria López');

        $this->assertSame(['1', '3600.00', '2512.88'], $this->cifras($valeria));
    }

    // CA4 · Sergio: solo Valeria y Diego; total del equipo.
    public function test_ca4_coordinador_sergio(): void
    {
        $sergio = $this->u('sergio');
        $filas = $this->reporte->porAsesor($sergio, '2026-10');

        $this->assertSame(['Diego Castillo', 'Valeria López'], $filas->pluck('name')->all());
        $this->assertSame(['3', '15250.00', '2625.38'], $this->cifras($this->reporte->totales($sergio, '2026-10')));
    }

    // CA5 · Sandra: Carlos e Iván (desactivado, en 0); total del equipo.
    public function test_ca5_coordinadora_sandra(): void
    {
        $sandra = $this->u('sandra');
        $filas = $this->reporte->porAsesor($sandra, '2026-10');

        $this->assertSame(['Carlos Ajú', 'Iván Toj'], $filas->pluck('name')->all());
        $this->assertSame(['0', '0.00', '0.00'], $this->cifras($this->fila($filas, 'Iván Toj')));
        $this->assertSame(['1', '7200.00', '4125.75'], $this->cifras($this->reporte->totales($sandra, '2026-10')));
    }

    // CA6 · Redondeo al final: el total de la clínica no es la suma de los redondeados.
    public function test_ca6_pronostico_de_grupo_se_redondea_al_final(): void
    {
        // Con estas cifras, sumar valores ya redondeados también daría 6751.13; lo que
        // se comprueba es que la suma parcial llega SIN redondear (Valeria 2512.875)
        // y que el grupo redondea una sola vez: 2512.875 + 112.50 = 2625.375 → 2625.38.
        $bruto = $this->reporte->pronosticoAbierto()->get()->pluck('bruto', 'vendedor_id');

        $this->assertSame('2512.875', rtrim((string) $bruto[$this->u('valeria')->id], '0'));
        $this->assertSame('2625.38', (string) $this->reporte->totales($this->u('sergio'), '2026-10')->pronostico);
    }

    // Un asesor no ve nada (el reporte es del panel).
    public function test_un_asesor_no_tiene_alcance(): void
    {
        $this->assertCount(0, $this->reporte->porAsesor($this->u('valeria'), '2026-10'));
    }
}
