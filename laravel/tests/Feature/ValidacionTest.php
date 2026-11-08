<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Support\Nit;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Spec 16 · Validación en el servidor (L7).
 */
class ValidacionTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
        $this->alicia = User::where('email', 'admin@crm.test')->firstOrFail();
    }

    /** Todos los mensajes de una bolsa de errores de la sesión. */
    private function mensajes(TestResponse $r, string $bolsa = 'default'): array
    {
        $errores = session('errors');
        $this->assertNotNull($errores, 'La respuesta no trajo errores de validación.');

        return $errores->getBag($bolsa)->all();
    }

    /** Ningún mensaje en inglés: ni palabras del framework ni textos de lang/en. */
    private function assertEnEspanol(array $mensajes): void
    {
        $this->assertNotEmpty($mensajes);
        $ingles = collect(require lang_path('en/validation.php'))->flatten()->all();

        foreach ($mensajes as $m) {
            $this->assertDoesNotMatchRegularExpression('/\b(the|field|must|required|invalid|selected|taken)\b/i', $m, "Mensaje en inglés: {$m}");
            $this->assertNotContains($m, $ingles, "Mensaje copiado de lang/en: {$m}");
            $this->assertStringEndsWith('.', $m, "Mensaje sin punto final: {$m}");
        }
    }

    // CA1 · Idioma y archivos

    public function test_ca1_el_idioma_del_panel_es_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('es', config('app.fallback_locale'));
        $this->assertSame('Complete el campo correo.', __('validation.required', ['attribute' => __('validation.attributes.email')]));
    }

    public function test_ca1_lang_es_tiene_las_mismas_claves_que_lang_en(): void
    {
        $claves = function (array $a, string $prefijo = '') use (&$claves): array {
            $r = [];
            foreach ($a as $k => $v) {
                if (is_array($v) && ! in_array($k, ['custom', 'attributes'], true)) {
                    $r = array_merge($r, $claves($v, "{$prefijo}{$k}."));
                } else {
                    $r[] = "{$prefijo}{$k}";
                }
            }

            return $r;
        };

        foreach (['validation', 'auth', 'passwords', 'pagination'] as $archivo) {
            $en = $claves(require lang_path("en/{$archivo}.php"));
            $es = $claves(require lang_path("es/{$archivo}.php"));
            sort($en);
            sort($es);
            $this->assertSame($en, $es, "lang/es/{$archivo}.php no tiene las mismas claves que lang/en.");
        }
    }

    // CA1 · Cada formulario enviado vacío

    public function test_ca1_login_vacio(): void
    {
        $r = $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->assertEnEspanol($this->mensajes($r));
    }

    public function test_ca1_cambiar_contrasena_vacio(): void
    {
        $r = $this->actingAs($this->alicia)->put('/password', [])
            ->assertSessionHasErrorsIn('updatePassword', ['current_password', 'password']);
        $this->assertEnEspanol($this->mensajes($r, 'updatePassword'));
    }

    public function test_ca1_cuenta_vacia(): void
    {
        $r = $this->actingAs($this->alicia)->post('/cuentas', [])
            ->assertSessionHasErrors(['name', 'email', 'password', 'rol', 'activo']);
        $this->assertEnEspanol($this->mensajes($r));
    }

    public function test_ca1_tratamiento_vacio(): void
    {
        $r = $this->actingAs($this->alicia)->post('/productos', [])
            ->assertSessionHasErrors(['nombre', 'precio', 'activo', 'requiere_evaluacion']);
        $this->assertEnEspanol($this->mensajes($r));
    }

    public function test_ca1_paciente_vacio(): void
    {
        $r = $this->actingAs($this->alicia)->post('/clientes', [])
            ->assertSessionHasErrors([
                'nombre' => 'Escriba el nombre del paciente.',
                'nit' => 'Escriba el NIT del paciente o CF.',
                'telefono' => 'Escriba el teléfono del paciente.',
            ]);
        $this->assertEnEspanol($this->mensajes($r));
    }

    public function test_ca1_asignar_vacio(): void
    {
        $cafe = Cliente::where('nombre', 'Café Zunil')->firstOrFail();

        $r = $this->actingAs($this->alicia)->patch("/clientes/{$cafe->id}/asignar", [])
            ->assertSessionHasErrorsIn('asignar'.$cafe->id, ['vendedor_id']);
        $this->assertEnEspanol($this->mensajes($r, 'asignar'.$cafe->id));
    }

    // CA2 · Correo no válido en el alta de paciente

    public function test_ca2_correo_no_valido_conserva_lo_escrito(): void
    {
        $this->actingAs($this->alicia)
            ->from('/clientes/create')
            ->post('/clientes', [
                'nombre' => 'Paciente de prueba',
                'nit' => 'CF',
                'telefono' => '7999-8877',
                'correo' => 'no-es-correo',
            ])
            ->assertRedirect('/clientes/create')
            ->assertSessionHasErrors(['correo' => 'Escriba un correo válido.'])
            ->assertSessionHasInput(['nombre' => 'Paciente de prueba', 'nit' => 'CF', 'telefono' => '7999-8877']);
    }

    // CA3 · Contraseña corta en el alta de cuenta

    public function test_ca3_contrasena_corta_no_se_vuelve_a_llenar(): void
    {
        $this->actingAs($this->alicia)
            ->from('/cuentas/create')
            ->post('/cuentas', [
                'name' => 'Cuenta de prueba',
                'email' => 'prueba16@crm.test',
                'password' => 'abcde',
                'password_confirmation' => 'abcde',
                'rol' => 'administrador',
                'activo' => '1',
            ])
            ->assertRedirect('/cuentas/create')
            ->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 8 caracteres.'])
            ->assertSessionHasInput(['name' => 'Cuenta de prueba', 'email' => 'prueba16@crm.test', 'rol' => 'administrador']);

        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());
        $this->assertSame(0, User::where('email', 'prueba16@crm.test')->count());
    }

    // CA4 · Las dos puertas

    public function test_ca4_telefono_guardado_por_la_api_choca_en_el_panel(): void
    {
        // Así lo guarda la API: ya normalizado, 8 dígitos.
        DB::table('clientes')->insert([
            'nombre' => 'Guardado por la API', 'nit' => null, 'telefono' => '55559876',
            'correo' => null, 'vendedor_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->alicia)
            ->post('/clientes', ['nombre' => 'Otro paciente', 'nit' => 'CF', 'telefono' => '+502 5555 9876'])
            ->assertSessionHasErrors(['telefono' => 'Ya existe un paciente con ese teléfono.']);
    }

    public function test_ca4_nit_guardado_en_el_panel_queda_como_lo_compara_la_api(): void
    {
        $this->actingAs($this->alicia)
            ->post('/clientes', ['nombre' => 'Paciente NIT', 'nit' => '76543218', 'telefono' => '79998877'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes', ['nombre' => 'Paciente NIT', 'nit' => '76543218']);
        $this->assertSame('76543218', Nit::normalizar('000-7654321-8'));

        $this->actingAs($this->alicia)
            ->post('/clientes', ['nombre' => 'Repetido', 'nit' => '000-7654321-8', 'telefono' => '79998878'])
            ->assertSessionHasErrors(['nit' => 'Ya existe un paciente con ese NIT.']);
    }

    // CA6 · Sin {!! !!} en las vistas

    public function test_ca6_ninguna_vista_usa_llaves_sin_escapar(): void
    {
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS));
        $conLlaves = [];

        foreach ($archivos as $archivo) {
            if (str_ends_with($archivo->getFilename(), '.blade.php') && str_contains(file_get_contents($archivo->getPathname()), '{!!')) {
                $conLlaves[] = $archivo->getPathname();
            }
        }

        $this->assertSame([], $conLlaves, 'Vistas con {!! !!}: '.implode(', ', $conLlaves));
    }

    // CA7 · Páginas de error y paginación en español

    public function test_ca7_404_en_espanol(): void
    {
        $this->actingAs($this->alicia)->get('/clientes/999999')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertDontSee('Not Found');
    }

    public function test_ca7_paginacion_en_espanol(): void
    {
        $this->actingAs($this->alicia)->get('/clientes')
            ->assertOk()
            ->assertSeeTextInOrder(['Mostrando', '1', 'a', '15', 'de', '21', 'resultados'])
            ->assertDontSee('Showing')
            ->assertDontSee('results');
    }
}
