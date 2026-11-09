<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reporte;
use App\Support\Nit;
use App\Support\Telefono;
use Database\Seeders\SectorSeeder;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Spec 17 · Semilla del sector (L1, capa de sector; datos de la sección 2.7).
 * Corre contra MySQL (crm_test). Cada prueba siembra dentro de su transacción,
 * igual que EsquemaYSemillaTest.
 *
 * Las cifras se comparan con el texto DECIMAL que devuelve MySQL, nunca con floats.
 */
class SemillaSectorTest extends TestCase
{
    use RefreshDatabase;

    /** Los 15 pacientes del sector (tabla 3.3 del spec). */
    private const PACIENTES = [
        'Sofía Ramírez', 'Jorge Cifuentes', 'Colegio Bilingüe Los Altos', 'Mariela Chun',
        'Rodrigo Tzoc', 'Gabriela Barrios', 'Esteban Quemé', 'Paola Fernández',
        'Textiles del Altiplano', 'Héctor Sapón', 'Claudia Ixcot', 'Marco Ajanel',
        'Fernando Pú', 'Daniela Monterroso', 'Andrés Velásquez',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Igual que DatabaseSeeder: primero la verificación y después el sector.
        $this->seed(VerificacionSeeder::class);
    }

    /** Siembra el sector. La prueba de R-P8 no lo llama. */
    private function conSector(): void
    {
        $this->seed(SectorSeeder::class);
    }

    private function conteos(): array
    {
        return array_map(fn (string $tabla) => DB::table($tabla)->count(), [
            'users', 'productos', 'clientes', 'oportunidades',
            'oportunidad_productos', 'historial_etapas', 'actividades',
        ]);
    }

    private function usuario(string $correo): User
    {
        return User::where('email', $correo)->firstOrFail();
    }

    /** [ventas, total, pronostico] como texto. */
    private function cifras(object $fila): array
    {
        return [(string) $fila->ventas, (string) $fila->total, (string) $fila->pronostico];
    }

    private function fila(Collection $filas, string $nombre): object
    {
        $fila = $filas->firstWhere('name', $nombre);
        $this->assertNotNull($fila, "No aparece la fila de {$nombre}.");

        return $fila;
    }

    /** Cuántas actividades `reunion` completadas tiene un paciente (sección 2.4). */
    private function evaluaciones(string $paciente): int
    {
        return DB::table('actividades')
            ->join('clientes', 'clientes.id', '=', 'actividades.cliente_id')
            ->where('clientes.nombre', $paciente)
            ->where('actividades.tipo', 'reunion')
            ->where('actividades.completada', true)
            ->count();
    }

    // CA1 · Conteos con las dos semillas.
    public function test_ca1_conteos_con_las_dos_semillas(): void
    {
        $this->conSector();

        $this->assertSame([10, 14, 36, 23, 25, 81, 11], $this->conteos());
    }

    // CA2 · Cuentas del sector: rol, coordinadora, activas y hash con la contraseña pública.
    public function test_ca2_cuentas_del_sector(): void
    {
        $this->conSector();

        $lucia = $this->usuario('lucia@dental.test');
        $andrea = $this->usuario('andrea@dental.test');
        $kevin = $this->usuario('kevin@dental.test');

        $this->assertSame('supervisor', $lucia->rol);
        $this->assertNull($lucia->supervisor_id);

        foreach ([$andrea, $kevin] as $asesor) {
            $this->assertSame('vendedor', $asesor->rol);
            $this->assertSame($lucia->id, $asesor->supervisor_id);
        }

        foreach ([$lucia, $andrea, $kevin] as $cuenta) {
            $this->assertTrue((bool) $cuenta->activo);
            $this->assertStringStartsWith('$2y$', $cuenta->password);
            $this->assertTrue(Hash::check(VerificacionSeeder::CONTRASENA, $cuenta->password));
        }
    }

    // CA3 · Solo ortodoncia e implante requieren evaluación.
    public function test_ca3_tratamientos_con_evaluacion(): void
    {
        $this->conSector();

        $this->assertSame(
            ['Implante dental', 'Ortodoncia con brackets'],
            DB::table('productos')->where('requiere_evaluacion', true)->orderBy('nombre')->pluck('nombre')->all()
        );
    }

    // CA4 · Cartera de 10 o menos y sin NIT, teléfonos ni correos repetidos.
    public function test_ca4_cartera_y_sin_repetidos(): void
    {
        $this->conSector();

        $carteras = DB::table('clientes')
            ->whereNotNull('vendedor_id')
            ->groupBy('vendedor_id')
            ->selectRaw('vendedor_id, COUNT(*) AS n')
            ->pluck('n', 'vendedor_id');

        foreach ($carteras as $n) {
            $this->assertLessThanOrEqual(10, (int) $n);
        }
        $this->assertSame(6, (int) $carteras[$this->usuario('andrea@dental.test')->id]);
        $this->assertSame(6, (int) $carteras[$this->usuario('kevin@dental.test')->id]);

        foreach ([['clientes', 'nit'], ['clientes', 'telefono'], ['users', 'email']] as [$tabla, $columna]) {
            $repetidos = DB::table($tabla)->whereNotNull($columna)
                ->groupBy($columna)->havingRaw('COUNT(*) > 1')->pluck($columna);
            $this->assertCount(0, $repetidos, "{$tabla}.{$columna} repetido: ".$repetidos->implode(', '));
        }
    }

    // CA5 · NIT y teléfonos del sector válidos con las mismas reglas del panel.
    public function test_ca5_nit_y_telefonos_validos(): void
    {
        $this->conSector();

        $pacientes = DB::table('clientes')->whereIn('nombre', self::PACIENTES)->get();
        $this->assertCount(15, $pacientes);

        foreach ($pacientes as $p) {
            if ($p->nit !== null) {
                $this->assertTrue(Nit::esValido($p->nit), "NIT no válido de {$p->nombre}: {$p->nit}");
            }
            $this->assertTrue(Telefono::esValido($p->telefono), "Teléfono no válido de {$p->nombre}: {$p->telefono}");
        }
    }

    // CA6 · Entre Andrea y Kevin aparecen las seis etapas.
    public function test_ca6_seis_etapas(): void
    {
        $this->conSector();

        $etapas = DB::table('oportunidades')
            ->whereIn('vendedor_id', [$this->usuario('andrea@dental.test')->id, $this->usuario('kevin@dental.test')->id])
            ->distinct()->orderBy('etapa')->pluck('etapa')->all();

        // etapa es ENUM: MySQL ordena por la posición en el ENUM (el orden del embudo).
        $this->assertSame(['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'], $etapas);
    }

    // CA7 · Historial cada 3 días, terminando en el cierre o en 2026-11-02 16:00 UTC.
    public function test_ca7_historial(): void
    {
        $this->conSector();

        $historial = fn (string $titulo) => DB::table('historial_etapas as h')
            ->join('oportunidades as o', 'o.id', '=', 'h.oportunidad_id')
            ->where('o.titulo', $titulo)
            ->orderBy('h.id')
            ->get(['h.etapa_anterior', 'h.etapa_nueva', 'h.created_at']);

        $perdida = $historial('Endodoncia y corona');
        $this->assertSame(['prospecto', 'calificado', 'propuesta', 'negociacion', 'perdida'], $perdida->pluck('etapa_nueva')->all());
        $this->assertNull($perdida[0]->etapa_anterior);
        $this->assertSame(
            ['2026-10-16 18:00:00', '2026-10-19 18:00:00', '2026-10-22 18:00:00', '2026-10-25 18:00:00', '2026-10-28 18:00:00'],
            $perdida->pluck('created_at')->all()
        );

        $abierta = $historial('Implante de premolar');
        $this->assertCount(4, $abierta);
        $this->assertSame('2026-11-02 16:00:00', $abierta->last()->created_at);
    }

    // CA8 · Citas que necesita la regla propia (tabla 3.6).
    public function test_ca8_citas_para_la_regla_propia(): void
    {
        $this->conSector();

        foreach (['Sofía Ramírez', 'Paola Fernández', 'Textiles del Altiplano', 'Marco Ajanel'] as $sinEvaluacion) {
            $this->assertSame(0, $this->evaluaciones($sinEvaluacion), "{$sinEvaluacion} no debe tener evaluación.");
        }
        foreach (['Jorge Cifuentes', 'Héctor Sapón'] as $conEvaluacion) {
            $this->assertSame(1, $this->evaluaciones($conEvaluacion), "{$conEvaluacion} debe tener evaluación.");
        }

        // Sofía tiene una cita pendiente (R-P3) y Marco ninguna actividad (R-P6).
        $sofia = DB::table('clientes')->where('nombre', 'Sofía Ramírez')->value('id');
        $this->assertSame(1, DB::table('actividades')->where('cliente_id', $sofia)->where('tipo', 'reunion')->where('completada', false)->count());
        $marco = DB::table('clientes')->where('nombre', 'Marco Ajanel')->value('id');
        $this->assertSame(0, DB::table('actividades')->where('cliente_id', $marco)->count());
    }

    // CA9 · Las cifras de 11.1 no cambian y las del sector son las de la tabla 3.7.
    public function test_ca9_cifras(): void
    {
        $this->conSector();

        $reporte = new Reporte;
        $admin = $this->usuario('admin@crm.test');
        $octubre = $reporte->porAsesor($admin, '2026-10');

        $this->assertSame(['2', '9250.00', '2512.88'], $this->cifras($this->fila($octubre, 'Valeria López')));
        $this->assertSame(['1', '6000.00', '112.50'], $this->cifras($this->fila($octubre, 'Diego Castillo')));
        $this->assertSame(['1', '7200.00', '4125.75'], $this->cifras($this->fila($octubre, 'Carlos Ajú')));

        $this->assertSame(['1', '7500.00', '9350.00'], $this->cifras($this->fila($octubre, 'Andrea Gómez')));
        $this->assertSame(['0', '0.00', '12050.00'], $this->cifras($this->fila($octubre, 'Kevin Chávez')));
        $this->assertSame(6, (int) $this->fila($octubre, 'Andrea Gómez')->cartera);
        $this->assertSame(6, (int) $this->fila($octubre, 'Kevin Chávez')->cartera);

        $noviembre = $reporte->porAsesor($admin, '2026-11');
        $this->assertSame(['1', '1050.00', '12050.00'], $this->cifras($this->fila($noviembre, 'Kevin Chávez')));

        $equipos = $reporte->porEquipo('2026-10');
        $this->assertSame(['1', '7500.00', '21400.00'], $this->cifras($this->fila($equipos, 'Dra. Lucía Méndez')));
        $this->assertSame(['3', '15250.00', '2625.38'], $this->cifras($this->fila($equipos, 'Sergio Ruiz')));
        $this->assertSame(['1', '7200.00', '4125.75'], $this->cifras($this->fila($equipos, 'Sandra Pérez')));

        $this->assertSame(['5', '29950.00', '28151.13'], $this->cifras($reporte->totales($admin, '2026-10')));
    }

    // CA10 · R-P8: con solo la semilla de verificación nada requiere evaluación.
    public function test_ca10_solo_verificacion_sin_evaluacion(): void
    {
        $this->assertSame(0, DB::table('productos')->where('requiere_evaluacion', true)->count());
        $this->assertSame([7, 5, 21, 11, 13, 43, 4], $this->conteos());
    }
}
