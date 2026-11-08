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

    // ── Servicio: por tratamiento ──────────────────────────────────────────

    /** Tratamientos como nombre => [unidades, total, pronostico] en texto. */
    private function tratamientos(User $quien, string $mes): array
    {
        return $this->reporte->porTratamiento($quien, $mes)
            ->mapWithKeys(fn (object $f) => [$f->nombre => [(string) $f->unidades, (string) $f->total, (string) $f->pronostico]])
            ->all();
    }

    // CA2 · Administrador, octubre: tabla por tratamiento de 11.2, con pronóstico, y su total.
    public function test_ca2_por_tratamiento_del_administrador(): void
    {
        $admin = $this->u('admin');

        $this->assertSame([
            'Instalación' => ['1', '450.00', '562.50'],
            'Plan avanzado' => ['2', '5200.00', '6188.63'],
            'Plan básico' => ['5', '6000.00', '0.00'],
            'Soporte anual' => ['3', '10800.00', '0.00'],
        ], $this->tratamientos($admin, '2026-10'));

        $total = $this->reporte->totalPorTratamiento($admin, '2026-10');
        $this->assertSame(['11', '22450.00', '6751.13'], [(string) $total->unidades, (string) $total->total, (string) $total->pronostico]);
    }

    // CA4 · Sergio, octubre: solo planes de su equipo; el total cuadra con el del equipo.
    public function test_ca4_por_tratamiento_de_sergio(): void
    {
        $sergio = $this->u('sergio');
        $filas = $this->tratamientos($sergio, '2026-10');

        $this->assertSame(['5', '6000.00'], array_slice($filas['Plan básico'], 0, 2));
        $this->assertSame(['2', '5200.00'], array_slice($filas['Plan avanzado'], 0, 2));
        $this->assertSame(['1', '450.00'], array_slice($filas['Instalación'], 0, 2));
        $this->assertSame(['1', '3600.00'], array_slice($filas['Soporte anual'], 0, 2));

        $total = $this->reporte->totalPorTratamiento($sergio, '2026-10');
        $this->assertSame(['15250.00', '2625.38'], [(string) $total->total, (string) $total->pronostico]);
    }

    // ── Página /reportes ───────────────────────────────────────────────────

    // CA1 · Alicia, octubre: filas por asesor, por equipo y toda la clínica en pantalla.
    public function test_ca1_pagina_del_administrador(): void
    {
        $this->actingAs($this->u('admin'))->get('/reportes?mes=2026-10')
            ->assertOk()
            ->assertSee('Reporte de octubre 2026')
            ->assertSee('Coordinador')
            ->assertSeeInOrder(['Carlos Ajú', 'Sandra Pérez', '9 de 10', '1', 'Q7,200.00', 'Q4,125.75'])
            ->assertSeeInOrder(['Iván Toj', 'Desactivado', 'Sandra Pérez', '1 de 10', '0', 'Q0.00', 'Q0.00'])
            ->assertSeeInOrder(['Diego Castillo', 'Sergio Ruiz', '2 de 10', '1', 'Q6,000.00', 'Q112.50'])
            ->assertSeeInOrder(['Valeria López', 'Sergio Ruiz', '3 de 10', '2', 'Q9,250.00', 'Q2,512.88'])
            ->assertSeeInOrder(['Toda la clínica', '4', 'Q22,450.00', 'Q6,751.13'])
            ->assertSeeInOrder(['Por equipo', 'Equipo de Sandra Pérez', '1', 'Q7,200.00', 'Q4,125.75',
                'Equipo de Sergio Ruiz', '3', 'Q15,250.00', 'Q2,625.38', 'Toda la clínica', '4', 'Q22,450.00', 'Q6,751.13']);
    }

    // CA2 · Alicia, octubre: tabla por tratamiento con pronóstico y su total.
    public function test_ca2_pagina_por_tratamiento(): void
    {
        $this->actingAs($this->u('admin'))->get('/reportes?mes=2026-10')
            ->assertOk()
            ->assertSeeInOrder(['Por tratamiento',
                'Instalación', '1', 'Q450.00', 'Q562.50',
                'Plan avanzado', '2', 'Q5,200.00', 'Q6,188.63',
                'Plan básico', '5', 'Q6,000.00', 'Q0.00',
                'Soporte anual', '3', 'Q10,800.00', 'Q0.00',
                'Total', '11', 'Q22,450.00', 'Q6,751.13']);
    }

    // CA3 · Noviembre: Valeria 1 · Q3,600.00 con el mismo pronóstico.
    public function test_ca3_pagina_noviembre(): void
    {
        $this->actingAs($this->u('admin'))->get('/reportes?mes=2026-11')
            ->assertOk()
            ->assertSee('Reporte de noviembre 2026')
            ->assertSeeInOrder(['Valeria López', 'Sergio Ruiz', '3 de 10', '1', 'Q3,600.00', 'Q2,512.88']);
    }

    // CA4 · Sergio: su equipo, sin columna de coordinador ni tabla por equipo.
    public function test_ca4_pagina_de_sergio(): void
    {
        $this->actingAs($this->u('sergio'))->get('/reportes?mes=2026-10')
            ->assertOk()
            ->assertSeeInOrder(['Diego Castillo', '2 de 10', '1', 'Q6,000.00', 'Q112.50'])
            ->assertSeeInOrder(['Valeria López', '3 de 10', '2', 'Q9,250.00', 'Q2,512.88'])
            ->assertSeeInOrder(['Total del equipo', '3', 'Q15,250.00', 'Q2,625.38'])
            ->assertSeeInOrder(['Por tratamiento', 'Instalación', '1', 'Q450.00', 'Plan avanzado', '2', 'Q5,200.00',
                'Plan básico', '5', 'Q6,000.00', 'Soporte anual', '1', 'Q3,600.00', 'Total', 'Q15,250.00', 'Q2,625.38'])
            ->assertDontSee('Carlos Ajú')
            ->assertDontSee('Iván Toj')
            ->assertDontSee('Coordinador')
            ->assertDontSee('Por equipo')
            ->assertDontSee('Toda la clínica');
    }

    // CA5 · Sandra: Carlos e Iván desactivado en 0; total del equipo.
    public function test_ca5_pagina_de_sandra(): void
    {
        $this->actingAs($this->u('sandra'))->get('/reportes?mes=2026-10')
            ->assertOk()
            ->assertSeeInOrder(['Carlos Ajú', '9 de 10', '1', 'Q7,200.00', 'Q4,125.75'])
            ->assertSeeInOrder(['Iván Toj', 'Desactivado', '1 de 10', '0', 'Q0.00', 'Q0.00'])
            ->assertSeeInOrder(['Total del equipo', '1', 'Q7,200.00', 'Q4,125.75'])
            ->assertDontSee('Valeria López')
            ->assertDontSee('Diego Castillo');
    }

    // CA7 · Sin mes: el actual de Guatemala. Mes no válido: aviso y mes actual.
    public function test_ca7_mes_por_omision_y_no_valido(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 03:00:00', 'UTC')); // 31 oct en Guatemala

        $this->actingAs($this->u('admin'))->get('/reportes')
            ->assertOk()
            ->assertSee('value="2026-10"', false)
            ->assertSee('Reporte de octubre 2026')
            ->assertSeeInOrder(['Toda la clínica', '4', 'Q22,450.00', 'Q6,751.13'])
            ->assertDontSee('Elija un mes válido');

        foreach (['/reportes?mes=2026-13', '/reportes?mes=abc', '/reportes?mes[]=2026-10'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Elija un mes válido (AAAA-MM).')
                ->assertSee('Reporte de octubre 2026');
        }
    }

    // CA10 · Menú "Reporte" para administrador y coordinador; sin sesión → login.
    public function test_ca10_menu_y_acceso(): void
    {
        foreach (['admin', 'sergio'] as $quien) {
            $this->actingAs($this->u($quien))->get('/profile')
                ->assertOk()
                ->assertSee('href="'.route('reportes.index').'"', false);
        }

        auth()->logout();
        $this->get('/reportes')->assertRedirect('/login');
    }

    // CA12 · XSS: el nombre de un asesor se muestra como texto.
    public function test_ca12_nombre_se_escapa(): void
    {
        $this->u('diego')->forceFill(['name' => '<b>x</b>'])->save();

        $this->actingAs($this->u('admin'))->get('/reportes?mes=2026-10')
            ->assertOk()
            ->assertSee('&lt;b&gt;x&lt;/b&gt;', false)
            ->assertDontSee('<b>x</b>', false);
    }

    // Un asesor no ve nada (el reporte es del panel).
    public function test_un_asesor_no_tiene_alcance(): void
    {
        $this->assertCount(0, $this->reporte->porAsesor($this->u('valeria'), '2026-10'));
    }
}
