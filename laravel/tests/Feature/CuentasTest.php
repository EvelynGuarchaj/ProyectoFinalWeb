<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Spec 03 · Cuentas (L2): CA3 a CA13, CA18 y CA20.
 */
class CuentasTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
        $this->alicia = $this->u('admin@crm.test');
    }

    private function u(string $correo): User
    {
        return User::where('email', $correo)->firstOrFail();
    }

    /** Datos válidos de un asesor nuevo del equipo de Sergio. */
    private function nuevoAsesor(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Rosa Xicará',
            'email' => 'rosa@crm.test',
            'password' => 'clave-de-prueba-03',
            'password_confirmation' => 'clave-de-prueba-03',
            'rol' => 'vendedor',
            'supervisor_id' => $this->u('sergio@crm.test')->id,
            'activo' => '1',
        ], $cambios);
    }

    /** Datos del formulario de edición tal como vienen de la base. */
    private function datosDe(User $cuenta, array $cambios = []): array
    {
        return array_merge([
            'name' => $cuenta->name,
            'email' => $cuenta->email,
            'password' => '',
            'password_confirmation' => '',
            'rol' => $cuenta->rol,
            'supervisor_id' => $cuenta->supervisor_id,
            'activo' => $cuenta->activo ? '1' : '0',
        ], $cambios);
    }

    private function crear(array $datos): TestResponse
    {
        return $this->actingAs($this->alicia)->from('/cuentas/create')->post('/cuentas', $datos);
    }

    private function editar(User $cuenta, array $datos): TestResponse
    {
        return $this->actingAs($this->alicia)
            ->from("/cuentas/{$cuenta->id}/edit")
            ->put("/cuentas/{$cuenta->id}", $datos);
    }

    /** Fila de users tal como está en la base, para comparar antes y después. */
    private function fila(User $cuenta): array
    {
        return (array) DB::table('users')->where('id', $cuenta->id)->first();
    }

    // CA3
    public function test_ca3_crear_asesor(): void
    {
        $this->crear($this->nuevoAsesor())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cuentas.index'))
            ->assertSessionHas('status', 'La cuenta se creó correctamente.');

        $rosa = $this->u('rosa@crm.test');
        $this->assertSame('vendedor', $rosa->rol);
        $this->assertSame($this->u('sergio@crm.test')->id, $rosa->supervisor_id);
        $this->assertTrue($rosa->activo);
        $this->assertStringStartsWith('$2y$', $rosa->password);

        $this->actingAs($this->alicia)->get('/cuentas')->assertOk()->assertSee('Rosa Xicará');
    }

    // CA4
    public function test_ca4_asesor_sin_coordinador(): void
    {
        $this->crear($this->nuevoAsesor(['supervisor_id' => '']))
            ->assertRedirect('/cuentas/create')
            ->assertSessionHasErrors(['supervisor_id' => 'Elija el coordinador del asesor.']);

        $this->assertDatabaseMissing('users', ['email' => 'rosa@crm.test']);

        // Vuelve con lo escrito, sin la contraseña.
        $this->actingAs($this->alicia)->get('/cuentas/create')
            ->assertSee('value="Rosa Xicará"', false)
            ->assertSee('value="rosa@crm.test"', false)
            ->assertDontSee('clave-de-prueba-03');
    }

    public function test_ca4_coordinador_que_no_es_coordinador_activo(): void
    {
        $sandra = $this->u('sandra@crm.test');

        foreach ([$this->u('valeria@crm.test')->id, 999999, 'abc'] as $id) {
            $this->crear($this->nuevoAsesor(['supervisor_id' => $id]))
                ->assertSessionHasErrors(['supervisor_id' => 'Elija un coordinador activo de la lista.']);
        }

        // Sandra desactivada tampoco sirve.
        DB::table('users')->where('id', $sandra->id)->update(['activo' => false]);
        $this->crear($this->nuevoAsesor(['supervisor_id' => $sandra->id]))
            ->assertSessionHasErrors(['supervisor_id' => 'Elija un coordinador activo de la lista.']);

        $this->assertDatabaseMissing('users', ['email' => 'rosa@crm.test']);
    }

    // CA5
    public function test_ca5_coordinador_vacio_para_otros_roles(): void
    {
        $this->crear($this->nuevoAsesor(['rol' => 'supervisor']))->assertSessionHasNoErrors();
        $this->assertNull($this->u('rosa@crm.test')->supervisor_id);

        $this->crear($this->nuevoAsesor(['email' => 'otra@crm.test', 'rol' => 'administrador']))->assertSessionHasNoErrors();
        $this->assertNull($this->u('otra@crm.test')->supervisor_id);
    }

    // CA6
    public function test_ca6_contrasena_al_crear(): void
    {
        $this->crear($this->nuevoAsesor(['password' => 'corta', 'password_confirmation' => 'corta']))
            ->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 8 caracteres.']);

        $this->crear($this->nuevoAsesor(['password_confirmation' => 'otra-distinta']))
            ->assertSessionHasErrors(['password' => 'La confirmación de la contraseña no coincide.']);

        $this->crear($this->nuevoAsesor(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors(['password' => 'Escriba la contraseña.']);

        $this->assertDatabaseMissing('users', ['email' => 'rosa@crm.test']);

        $this->actingAs($this->alicia)->get('/cuentas/create')
            ->assertSee('value="rosa@crm.test"', false)
            ->assertDontSee('clave-de-prueba-03');
    }

    // CA7
    public function test_ca7_contrasena_opcional_al_editar(): void
    {
        $diego = $this->u('diego@crm.test');
        $hash = $diego->password;

        $this->editar($diego, $this->datosDe($diego, ['name' => 'Diego Castillo Pérez']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cuentas.index'))
            ->assertSessionHas('status', 'La cuenta se actualizó correctamente.');

        $this->assertSame($hash, $diego->fresh()->password);
        $this->assertSame('Diego Castillo Pérez', $diego->fresh()->name);
    }

    public function test_ca7_contrasena_nueva_al_editar(): void
    {
        $diego = $this->u('diego@crm.test');

        $this->editar($diego, $this->datosDe($diego, [
            'password' => 'nueva-clave-03',
            'password_confirmation' => 'nueva-clave-03',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('nueva-clave-03', $diego->fresh()->password));
        $this->assertStringStartsWith('$2y$', $diego->fresh()->password);
    }

    // CA8
    public function test_ca8_correo_unico(): void
    {
        $this->crear($this->nuevoAsesor(['email' => 'valeria@crm.test']))
            ->assertSessionHasErrors(['email' => 'Ya existe una cuenta con ese correo.']);

        $valeria = $this->u('valeria@crm.test');
        $this->editar($valeria, $this->datosDe($valeria))->assertSessionHasNoErrors();
    }

    // CA9
    public function test_ca9_el_administrador_no_se_toca_a_si_mismo(): void
    {
        $antes = $this->fila($this->alicia);

        $this->editar($this->alicia, $this->datosDe($this->alicia, ['activo' => '0']))
            ->assertSessionHasErrors(['activo' => 'No puede desactivar su propia cuenta.']);

        $this->editar($this->alicia, $this->datosDe($this->alicia, ['rol' => 'supervisor']))
            ->assertSessionHasErrors(['rol' => 'No puede cambiar su propio rol.']);

        $this->assertSame($antes, $this->fila($this->alicia));
    }

    // CA10
    public function test_ca10_coordinador_con_asesores(): void
    {
        $sergio = $this->u('sergio@crm.test');
        $antes = $this->fila($sergio);

        $this->editar($sergio, $this->datosDe($sergio, ['activo' => '0']))
            ->assertSessionHasErrors(['activo' => 'No puede desactivar a un coordinador que tiene asesores. Primero cámbielos de coordinador.']);

        $this->editar($sergio, $this->datosDe($sergio, ['rol' => 'administrador']))
            ->assertSessionHasErrors(['rol' => 'No puede cambiar el rol de un coordinador que tiene asesores. Primero cámbielos de coordinador.']);

        $this->assertSame($antes, $this->fila($sergio));

        // Sandra: Carlos activo e Iván desactivado; los dos cuentan.
        $sandra = $this->u('sandra@crm.test');
        $this->editar($sandra, $this->datosDe($sandra, ['activo' => '0']))
            ->assertSessionHasErrors('activo');
        $this->assertTrue($sandra->fresh()->activo);

        // Con solo Iván (desactivado) en su equipo, sigue sin poder desactivarse.
        $this->u('carlos@crm.test')->update(['supervisor_id' => $sergio->id]);
        $this->editar($sandra, $this->datosDe($sandra, ['activo' => '0']))
            ->assertSessionHasErrors('activo');
        $this->assertTrue($sandra->fresh()->activo);
    }

    public function test_ca10_coordinador_sin_asesores_si_se_desactiva(): void
    {
        $sandra = $this->u('sandra@crm.test');
        $sergio = $this->u('sergio@crm.test');
        User::whereIn('email', ['carlos@crm.test', 'ivan@crm.test'])->update(['supervisor_id' => $sergio->id]);

        $this->editar($sandra, $this->datosDe($sandra, ['activo' => '0']))->assertSessionHasNoErrors();
        $this->assertFalse($sandra->fresh()->activo);
    }

    public function test_un_coordinador_no_puede_ser_su_propio_coordinador(): void
    {
        $sandra = $this->u('sandra@crm.test');
        User::whereIn('email', ['carlos@crm.test', 'ivan@crm.test'])
            ->update(['supervisor_id' => $this->u('sergio@crm.test')->id]);

        $this->editar($sandra, $this->datosDe($sandra, ['rol' => 'vendedor', 'supervisor_id' => $sandra->id]))
            ->assertSessionHasErrors(['supervisor_id' => 'Elija un coordinador activo de la lista.']);
        $this->assertSame('supervisor', $sandra->fresh()->rol);
    }

    // CA11
    public function test_ca11_asesor_con_pacientes(): void
    {
        $valeria = $this->u('valeria@crm.test');
        $antes = $this->fila($valeria);
        $this->assertSame(3, Cliente::where('vendedor_id', $valeria->id)->count());

        $this->editar($valeria, $this->datosDe($valeria, ['rol' => 'supervisor']))
            ->assertSessionHasErrors(['rol' => 'No puede cambiar el rol de un asesor que tiene pacientes. Primero reasígnelos o libérelos.']);
        $this->assertSame($antes, $this->fila($valeria));

        $this->editar($valeria, $this->datosDe($valeria, ['activo' => '0']))->assertSessionHasNoErrors();
        $this->assertFalse($valeria->fresh()->activo);
        $this->assertSame(3, Cliente::where('vendedor_id', $valeria->id)->count());
    }

    // CA12
    public function test_ca12_no_se_borran_cuentas(): void
    {
        $valeria = $this->u('valeria@crm.test');

        $this->actingAs($this->alicia)->delete("/cuentas/{$valeria->id}")->assertStatus(405);
        $this->assertNotNull($valeria->fresh());

        $this->actingAs($this->alicia)->get('/cuentas')->assertDontSee('Borrar')->assertDontSee('Eliminar');
    }

    // CA13
    public function test_ca13_lista_y_filtro(): void
    {
        $respuesta = $this->actingAs($this->alicia)->get('/cuentas')->assertOk();

        $this->assertCount(7, $respuesta->viewData('cuentas'));
        foreach (['Alicia Morales', 'Sergio Ruiz', 'Sandra Pérez', 'Valeria López', 'Diego Castillo', 'Carlos Ajú', 'Iván Toj'] as $nombre) {
            $respuesta->assertSee($nombre);
        }
        $respuesta->assertSee('Administrador')->assertSee('Coordinador')->assertSee('Asesor')->assertSee('Desactivada');

        $ivan = $respuesta->viewData('cuentas')->firstWhere('email', 'ivan@crm.test');
        $this->assertSame('Sandra Pérez', $ivan->supervisor->name);
        $this->assertFalse($ivan->activo);

        $filtrada = $this->actingAs($this->alicia)->get('/cuentas?rol=vendedor')->assertOk();
        $this->assertEqualsCanonicalizing(
            ['valeria@crm.test', 'diego@crm.test', 'carlos@crm.test', 'ivan@crm.test'],
            $filtrada->viewData('cuentas')->pluck('email')->all(),
        );

        // Un rol que no existe se ignora.
        $this->assertCount(7, $this->actingAs($this->alicia)->get('/cuentas?rol=otro')->viewData('cuentas'));
    }

    // CA18
    public function test_ca18_el_coordinador_recibe_403(): void
    {
        $sergio = $this->u('sergio@crm.test');
        $valeria = $this->u('valeria@crm.test');

        $this->actingAs($sergio)->get('/cuentas')->assertForbidden();
        $this->actingAs($sergio)->get('/cuentas/create')->assertForbidden();
        $this->actingAs($sergio)->get("/cuentas/{$valeria->id}/edit")->assertForbidden();
        $this->actingAs($sergio)->post('/cuentas', $this->nuevoAsesor())->assertForbidden();
        $this->actingAs($sergio)->put("/cuentas/{$valeria->id}", $this->datosDe($valeria, ['activo' => '0']))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'rosa@crm.test']);
        $this->assertTrue($valeria->fresh()->activo);
    }

    public function test_sin_sesion_va_al_login(): void
    {
        $this->get('/cuentas')->assertRedirect(route('login'));
    }

    // CA20
    public function test_ca20_xss_en_el_nombre(): void
    {
        $this->crear($this->nuevoAsesor(['name' => '<script>alert(1)</script>']))->assertSessionHasNoErrors();

        $this->actingAs($this->alicia)->get('/cuentas')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_formulario_de_edicion_no_ofrece_a_la_propia_cuenta_como_coordinador(): void
    {
        $sergio = $this->u('sergio@crm.test');

        $supervisores = $this->actingAs($this->alicia)->get("/cuentas/{$sergio->id}/edit")
            ->assertOk()
            ->assertSee('Déjela vacía para conservar la actual.')
            ->viewData('supervisores');

        $this->assertSame(['Sandra Pérez'], $supervisores->pluck('name')->all());
    }
}
