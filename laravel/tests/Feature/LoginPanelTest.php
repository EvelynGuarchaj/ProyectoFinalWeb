<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Spec 03 · Login por puerta (L2): CA14, CA15, CA16 y CA17, el middleware
 * 'rol' y el cambio de contraseña del perfil en español.
 *
 * La contraseña pública de la semilla no se escribe aquí: cada prueba que
 * inicia sesión cambia antes el hash de la cuenta por una clave propia.
 */
class LoginPanelTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'clave-de-prueba-03';

    private const MSG_CREDENCIALES = 'El correo o la contraseña no son correctos.';

    private const MSG_ASESOR = 'Esta cuenta es de asesor. Ingrese desde la aplicación de asesores.';

    private const MSG_DESACTIVADA = 'Su cuenta está desactivada. Comuníquese con el administrador.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
    }

    private function cuenta(string $correo): User
    {
        $usuario = User::where('email', $correo)->firstOrFail();
        $usuario->forceFill(['password' => Hash::make(self::CLAVE)])->save();

        return $usuario;
    }

    private function entrar(string $correo, string $clave, array $extra = [])
    {
        return $this->from('/login')->post('/login', ['email' => $correo, 'password' => $clave] + $extra);
    }

    public function test_el_administrador_entra_al_panel(): void
    {
        $alicia = $this->cuenta('admin@crm.test');

        $this->entrar('admin@crm.test', self::CLAVE)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($alicia);
    }

    public function test_el_coordinador_entra_al_panel(): void
    {
        $sergio = $this->cuenta('sergio@crm.test');

        $this->entrar('sergio@crm.test', self::CLAVE)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($sergio);
    }

    // CA1
    public function test_ca1_sin_registro_ni_restablecer_contrasena(): void
    {
        foreach (['/register', '/forgot-password', '/reset-password/x', '/verify-email', '/confirm-password'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/register', ['email' => 'nuevo@crm.test'])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@crm.test']);

        $this->get('/login')->assertOk()
            ->assertDontSee('Register')->assertDontSee('Registrarse')
            ->assertDontSee('Forgot your password?')->assertDontSee('olvidó', false);

        $this->get('/')->assertOk()
            ->assertDontSee('Register')
            ->assertSee('Iniciar sesión');
    }

    // CA14
    public function test_ca14_un_asesor_no_entra_al_panel(): void
    {
        $this->cuenta('valeria@crm.test');

        $respuesta = $this->entrar('valeria@crm.test', self::CLAVE, ['remember' => 'on']);

        $respuesta->assertRedirect('/login')->assertSessionHasErrors(['email' => self::MSG_ASESOR]);
        $this->assertGuest();
        // "Recordarme" no deja una cookie útil: logout() la olvida.
        $respuesta->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    // CA15
    public function test_ca15_una_cuenta_desactivada_no_entra(): void
    {
        // Iván es asesor y está desactivado: gana el mensaje de desactivada.
        $this->cuenta('ivan@crm.test');

        $this->entrar('ivan@crm.test', self::CLAVE)
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => self::MSG_DESACTIVADA]);

        $this->assertGuest();
    }

    public function test_ca15_un_coordinador_desactivado_no_entra(): void
    {
        $sandra = $this->cuenta('sandra@crm.test');
        $sandra->forceFill(['activo' => false])->save();

        $this->entrar('sandra@crm.test', self::CLAVE)
            ->assertSessionHasErrors(['email' => self::MSG_DESACTIVADA]);

        $this->assertGuest();
    }

    // CA16
    public function test_ca16_correo_inexistente(): void
    {
        $this->entrar('nadie@crm.test', self::CLAVE)
            ->assertSessionHasErrors(['email' => self::MSG_CREDENCIALES]);

        $this->assertGuest();
    }

    public function test_ca16_contrasena_incorrecta_de_coordinador_y_de_asesor(): void
    {
        $this->cuenta('sergio@crm.test');
        $this->cuenta('valeria@crm.test');

        $this->entrar('sergio@crm.test', 'otra-clave-incorrecta')
            ->assertSessionHasErrors(['email' => self::MSG_CREDENCIALES]);
        $this->assertGuest();

        $this->entrar('valeria@crm.test', 'otra-clave-incorrecta')
            ->assertSessionHasErrors(['email' => self::MSG_CREDENCIALES]);
        $this->assertGuest();
    }

    public function test_mensajes_de_validacion_en_espanol(): void
    {
        $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => 'Escriba su correo.',
                'password' => 'Escriba su contraseña.',
            ]);

        $this->from('/login')->post('/login', ['email' => 'no-es-correo', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'Escriba un correo válido.']);
    }

    public function test_demasiados_intentos(): void
    {
        $this->cuenta('sergio@crm.test');

        for ($i = 0; $i < 5; $i++) {
            $this->entrar('sergio@crm.test', 'otra-clave-incorrecta');
        }

        $this->entrar('sergio@crm.test', self::CLAVE)
            ->assertSessionHasErrors('email');

        $this->assertStringStartsWith('Demasiados intentos. Intente de nuevo en ', session('errors')->first('email'));
        $this->assertStringEndsWith(' segundos.', session('errors')->first('email'));
        $this->assertGuest();

        RateLimiter::clear('sergio@crm.test|127.0.0.1');
    }

    public function test_cambio_de_contrasena_con_mensajes_en_espanol(): void
    {
        $alicia = $this->cuenta('admin@crm.test');

        $this->actingAs($alicia)->from('/profile')->put('/password', [
            'current_password' => 'no-es-la-actual',
            'password' => 'corta',
            'password_confirmation' => 'distinta',
        ])->assertSessionHasErrorsIn('updatePassword', [
            'current_password' => 'La contraseña actual no es correcta.',
            'password' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $this->actingAs($alicia)->from('/profile')->put('/password', [
            'current_password' => self::CLAVE,
            'password' => 'nueva-clave-03',
            'password_confirmation' => 'nueva-clave-03',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertTrue(Hash::check('nueva-clave-03', $alicia->fresh()->password));
    }

    // ---------------------------------------------------------------
    // CA17 · Sesión que se pierde (middleware 'panel')
    // ---------------------------------------------------------------

    private function assertFueraDelPanel($respuesta): void
    {
        $respuesta->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => self::MSG_DESACTIVADA]);
        $this->assertGuest();
    }

    public function test_ca17_coordinador_desactivado_pierde_la_sesion(): void
    {
        $sergio = $this->cuenta('sergio@crm.test');
        $this->actingAs($sergio)->get('/profile')->assertOk();

        // UPDATE directo: el panel no deja desactivar a un coordinador con asesores.
        DB::table('users')->where('id', $sergio->id)->update(['activo' => false]);

        $this->assertFueraDelPanel($this->get('/profile'));
    }

    public function test_ca17_coordinador_que_pasa_a_vendedor_pierde_la_sesion(): void
    {
        $sergio = $this->cuenta('sergio@crm.test');
        $sandra = User::where('email', 'sandra@crm.test')->firstOrFail();
        $this->actingAs($sergio)->get('/dashboard')->assertOk();

        // chk_users_supervisor exige coordinador al pasar a vendedor.
        DB::table('users')->where('id', $sergio->id)
            ->update(['rol' => 'vendedor', 'supervisor_id' => $sandra->id]);

        $this->assertFueraDelPanel($this->get('/dashboard'));
    }

    public function test_ca17_aplica_a_todas_las_rutas_del_panel(): void
    {
        $alicia = $this->cuenta('admin@crm.test');

        foreach ([['get', '/dashboard'], ['get', '/profile'], ['put', '/password'], ['post', '/logout']] as [$metodo, $url]) {
            DB::table('users')->where('id', $alicia->id)->update(['activo' => false]);
            $this->actingAs($alicia);

            $this->assertFueraDelPanel($this->{$metodo}($url));

            DB::table('users')->where('id', $alicia->id)->update(['activo' => true]);
        }
    }

    public function test_ca17_cuenta_que_ya_no_existe_pierde_la_sesion(): void
    {
        $temporal = User::factory()->create();
        $this->actingAs($temporal);

        DB::table('users')->where('id', $temporal->id)->delete();

        $this->assertFueraDelPanel($this->get('/profile'));
    }

    // ---------------------------------------------------------------
    // Middleware 'rol' (las rutas reales llegan en el paso 4 y specs futuros)
    // ---------------------------------------------------------------

    public function test_rol_responde_403_a_un_rol_que_no_esta_en_la_lista(): void
    {
        Route::middleware(['web', 'auth', 'panel', 'rol:administrador'])
            ->get('/_prueba-rol-admin', fn () => 'ok');
        Route::middleware(['web', 'auth', 'panel', 'rol:administrador,supervisor'])
            ->get('/_prueba-rol-ambos', fn () => 'ok');

        $alicia = $this->cuenta('admin@crm.test');
        $sergio = $this->cuenta('sergio@crm.test');

        $this->actingAs($alicia)->get('/_prueba-rol-admin')->assertOk();
        $this->actingAs($alicia)->get('/_prueba-rol-ambos')->assertOk();
        $this->actingAs($sergio)->get('/_prueba-rol-admin')->assertForbidden();
        $this->actingAs($sergio)->get('/_prueba-rol-ambos')->assertOk();
    }
}
