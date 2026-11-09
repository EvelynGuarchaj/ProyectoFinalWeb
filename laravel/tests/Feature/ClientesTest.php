<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\VerificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Spec 05 · Pacientes (L4 parte 1, S5 de pacientes, sección 10 en el panel).
 */
class ClientesTest extends TestCase
{
    use RefreshDatabase;

    private User $alicia;

    private User $sergio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VerificacionSeeder::class);
        $this->alicia = $this->u('admin@crm.test');
        $this->sergio = $this->u('sergio@crm.test');
    }

    private function u(string $correo): User
    {
        return User::where('email', $correo)->firstOrFail();
    }

    private function paciente(string $nombre): Cliente
    {
        return Cliente::where('nombre', $nombre)->firstOrFail();
    }

    /** Nombres de los pacientes de la lista (todas las páginas no: solo la que devuelve). */
    private function nombresEn(TestResponse $r): array
    {
        return $r->viewData('clientes')->getCollection()->pluck('nombre')->all();
    }

    // CA3 · Lista del administrador

    public function test_ca3_alicia_ve_21_pacientes_en_dos_paginas(): void
    {
        $r = $this->actingAs($this->alicia)->get('/clientes')
            ->assertOk()
            ->assertSee('Pacientes')
            ->assertSee('21 pacientes');
        $this->assertCount(15, $this->nombresEn($r));

        $r2 = $this->actingAs($this->alicia)->get('/clientes?page=2')->assertOk();
        $this->assertCount(6, $this->nombresEn($r2));
    }

    public function test_ca3_filtro_sin_asignar_e_ivan(): void
    {
        $r = $this->actingAs($this->alicia)->get('/clientes?asesor=sin')->assertSee('6 pacientes');
        $this->assertEqualsCanonicalizing(
            ['Agencia Cuatro Caminos', 'Café Zunil', 'Clínica San Rafael', 'Constructora Altiplano', 'Escuela Futuro', 'Gimnasio Fuerza'],
            $this->nombresEn($r),
        );

        $ivan = $this->u('ivan@crm.test');
        $r = $this->actingAs($this->alicia)->get('/clientes?asesor='.$ivan->id)->assertSee('1 paciente');
        $this->assertSame(['Hotel Los Pinos'], $this->nombresEn($r));
    }

    public function test_ca3_cf_y_disponible_en_pantalla(): void
    {
        $this->actingAs($this->alicia)->get('/clientes?q=Coyoy')
            ->assertSeeInOrder(['María Coyoy', 'CF', '41234567', 'Valeria López']);

        $this->actingAs($this->alicia)->get('/clientes?q=Zunil')
            ->assertSeeInOrder(['Café Zunil', '30002002', 'Disponible']);
    }

    public function test_ca3_el_filtro_del_administrador_ofrece_a_todos_los_asesores(): void
    {
        $this->actingAs($this->alicia)->get('/clientes')
            ->assertSeeInOrder(['Sin asignar', 'Carlos Ajú', 'Diego Castillo', 'Iván Toj (desactivado)', 'Valeria López']);
    }

    // CA4 · Lista del coordinador

    public function test_ca4_sergio_ve_11_y_no_ve_otro_equipo(): void
    {
        $r = $this->actingAs($this->sergio)->get('/clientes')->assertOk()->assertSee('11 pacientes');
        $this->assertNotContains('Abarrotes San Juan', $this->nombresEn($r));

        $this->assertSame(11, Cliente::visiblePara($this->sergio)->count());
        $this->assertSame(0, Cliente::visiblePara($this->sergio)
            ->whereIn('vendedor_id', [$this->u('carlos@crm.test')->id, $this->u('ivan@crm.test')->id])->count());
    }

    public function test_ca4_el_filtro_de_sergio_solo_ofrece_su_equipo(): void
    {
        $asesores = $this->actingAs($this->sergio)->get('/clientes')->viewData('asesores')->pluck('name')->all();
        $this->assertSame(['Diego Castillo', 'Valeria López'], $asesores);

        $this->actingAs($this->sergio)->get('/clientes')
            ->assertDontSee('Carlos Ajú')
            ->assertDontSee('Iván Toj');
    }

    public function test_ca4_asesor_ajeno_escrito_a_mano_da_lista_vacia(): void
    {
        $carlos = $this->u('carlos@crm.test');
        $this->actingAs($this->sergio)->get('/clientes?asesor='.$carlos->id)
            ->assertOk()
            ->assertSee('0 pacientes')
            ->assertSee('No hay pacientes que coincidan con la búsqueda.')
            ->assertDontSee('Abarrotes San Juan');
    }

    public function test_ca4_coordinador_ve_pacientes_de_su_asesor_desactivado(): void
    {
        $sandra = $this->u('sandra@crm.test');
        $this->actingAs($sandra)->get('/clientes')->assertSee('Hotel Los Pinos');
    }

    // CA5 · Búsqueda

    public function test_ca5_busca_por_nit_con_guion_y_por_nombre(): void
    {
        foreach (['4521817-k', 'quetzal', ' 0004521817 '] as $q) {
            $r = $this->actingAs($this->alicia)->get('/clientes?q='.urlencode($q));
            $this->assertContains('Comercial El Quetzal', $this->nombresEn($r), $q);
        }
    }

    public function test_ca5_busqueda_y_filtro_se_combinan(): void
    {
        $r = $this->actingAs($this->alicia)->get('/clientes?q=quetzal&asesor=sin');
        $this->assertSame([], $this->nombresEn($r));
    }

    public function test_ca5_los_comodines_se_buscan_literales(): void
    {
        $this->actingAs($this->alicia)->get('/clientes?q=%25')->assertSee('0 pacientes');
        $this->actingAs($this->alicia)->get('/clientes?q=_')->assertSee('0 pacientes');
    }

    // CA6 · Lo ajeno da 404 (aquí el alcance; las URLs de detalle y edición, en el paso 5)

    public function test_ca6_el_alcance_de_sergio_no_encuentra_pacientes_de_otro_equipo(): void
    {
        $abarrotes = $this->paciente('Abarrotes San Juan');

        $this->assertNull(Cliente::visiblePara($this->sergio)->find($abarrotes->id));
        $this->assertNotNull(Cliente::visiblePara($this->alicia)->find($abarrotes->id));
        $this->assertNotNull(Cliente::visiblePara($this->sergio)->find($this->paciente('Café Zunil')->id));
        $this->assertSame(0, Cliente::visiblePara($this->u('valeria@crm.test'))->count());
    }

    public function test_el_asesor_no_entra_a_pacientes_del_panel(): void
    {
        // El middleware panel (spec 03) saca al asesor antes de llegar a la lista.
        $this->actingAs($this->u('valeria@crm.test'))->get('/clientes')
            ->assertRedirect(route('login'))
            ->assertDontSee('Comercial El Quetzal');
    }

    // ---- Alta (paso 4) ----

    /** Datos válidos de un paciente nuevo que no choca con la semilla. */
    private function nuevo(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Paciente Prueba',
            'nit' => 'CF',
            'telefono' => '7761-9999',
            'correo' => '',
        ], $cambios);
    }

    private function crear(array $datos, ?User $quien = null): TestResponse
    {
        return $this->actingAs($quien ?? $this->alicia)
            ->from('/clientes/create')
            ->post('/clientes', $datos);
    }

    // CA7 · NIT y teléfono repetidos

    public function test_ca7_nit_repetido_vuelve_con_lo_escrito(): void
    {
        $antes = Cliente::count();

        $this->crear($this->nuevo(['nit' => '4521817-k']))
            ->assertRedirect('/clientes/create')
            ->assertSessionHasErrors(['nit' => 'Ya existe un paciente con ese NIT.'])
            ->assertSessionHasInput('nit', '4521817-k');

        $this->assertSame($antes, Cliente::count());
    }

    public function test_ca7_telefono_repetido_vuelve_con_lo_escrito(): void
    {
        $antes = Cliente::count();

        $this->crear($this->nuevo(['telefono' => '+502 5555-1234']))
            ->assertSessionHasErrors(['telefono' => 'Ya existe un paciente con ese teléfono.'])
            ->assertSessionHasInput('telefono', '+502 5555-1234');

        $this->assertSame($antes, Cliente::count());
    }

    // CA8 · NIT y teléfono inválidos

    public function test_ca8_nit_y_telefono_invalidos(): void
    {
        $antes = Cliente::count();

        $this->crear($this->nuevo(['nit' => '4521817-1', 'telefono' => '1234-5678']))
            ->assertSessionHasErrors([
                'nit' => 'Escriba un NIT válido (con su dígito verificador) o CF.',
                'telefono' => 'Escriba un teléfono de Guatemala de 8 dígitos.',
            ])
            ->assertSessionHasInput('nit', '4521817-1')
            ->assertSessionHasInput('telefono', '1234-5678')
            ->assertSessionHasInput('nombre', 'Paciente Prueba');

        $this->assertSame($antes, Cliente::count());
    }

    public function test_ca8_los_mensajes_aparecen_junto_al_campo(): void
    {
        $this->crear($this->nuevo(['nit' => '4521817-1']));

        $this->actingAs($this->alicia)->get('/clientes/create')
            ->assertSeeInOrder(['NIT', 'value="4521817-1"', 'Escriba un NIT válido (con su dígito verificador) o CF.', 'Teléfono'], false);
    }

    // CA9 · CF y prefijo

    public function test_ca9_cf_y_telefono_con_prefijo(): void
    {
        $this->crear($this->nuevo(['nit' => 'cf', 'telefono' => '(502) 7761.9999']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'El paciente se creó correctamente.');

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Paciente Prueba',
            'nit' => null,
            'telefono' => '77619999',
            'vendedor_id' => null,
            'correo' => null,
        ]);

        $this->actingAs($this->alicia)->get('/clientes?q=Paciente+Prueba')
            ->assertSeeInOrder(['Paciente Prueba', 'CF', '77619999', 'Disponible']);
    }

    public function test_ca9_varios_cf_no_chocan(): void
    {
        $this->crear($this->nuevo(['nit' => 'CF']))->assertSessionHasNoErrors();
        $this->crear($this->nuevo(['nombre' => 'Otro CF', 'nit' => 'cf', 'telefono' => '7761-9998']))->assertSessionHasNoErrors();

        $this->assertSame(2, Cliente::whereIn('telefono', ['77619999', '77619998'])->whereNull('nit')->count());
    }

    // CA10 · Sección 10 en el formulario

    /** @return array<string, array{string, string, ?string, ?string}> campo, entrada, se guarda como, error */
    public static function seccion10(): array
    {
        $nitMal = 'Escriba un NIT válido (con su dígito verificador) o CF.';
        $telMal = 'Escriba un teléfono de Guatemala de 8 dígitos.';

        return [
            'nit 452 1817-k' => ['nit', '452 1817-k', '4521817K', null],   // se prueba sin el de la semilla
            'nit 4521817-K repetido' => ['nit', '4521817-K', null, 'Ya existe un paciente con ese NIT.'],
            'nit 00012345679' => ['nit', '00012345679', '12345679', null],  // se prueba sin el de la semilla
            'nit cf' => ['nit', 'cf', null, null],
            'nit CF' => ['nit', 'CF', null, null],
            'nit 4521817-1' => ['nit', '4521817-1', null, $nitMal],
            'tel +502 5555-1234' => ['telefono', '+502 5555-1234', '55551234', null],
            'tel (502) 7761.2345' => ['telefono', '(502) 7761.2345', '77612345', null],
            'tel 50255551234 repetido' => ['telefono', '50255551234', null, 'Ya existe un paciente con ese teléfono.'],
            'tel 5021 2345' => ['telefono', '5021 2345', '50212345', null],
            'tel 1234-5678' => ['telefono', '1234-5678', null, $telMal],
            'tel 5555123' => ['telefono', '5555123', null, $telMal],
        ];
    }

    #[DataProvider('seccion10')]
    public function test_ca10_seccion_10_en_el_alta(string $campo, string $entrada, ?string $guardado, ?string $error): void
    {
        // Para probar que se guarda, se libera el valor que la semilla ya usa.
        if ($error === null && $guardado !== null) {
            Cliente::where($campo, $guardado)->update([$campo => $campo === 'nit' ? null : '29999999']);
        }

        $antes = Cliente::count();
        $r = $this->crear($this->nuevo([$campo => $entrada]));

        if ($error !== null) {
            $r->assertSessionHasErrors([$campo => $error]);
            $this->assertSame($antes, Cliente::count());

            return;
        }

        $r->assertSessionHasNoErrors();
        $creado = Cliente::where('nombre', 'Paciente Prueba')->firstOrFail();
        $this->assertSame($guardado, $creado->{$campo});
    }

    // CA11 · Obligatorios, correo y vendedor_id

    public function test_ca11_alta_vacia_en_espanol(): void
    {
        $this->crear(['nombre' => '', 'nit' => '', 'telefono' => '', 'correo' => ''])
            ->assertSessionHasErrors([
                'nombre' => 'Escriba el nombre del paciente.',
                'nit' => 'Escriba el NIT del paciente o CF.',
                'telefono' => 'Escriba el teléfono del paciente.',
            ])
            ->assertSessionDoesntHaveErrors('correo');

        $errores = session('errors')->getBag('default')->all();
        $this->assertCount(3, $errores);
        foreach ($errores as $mensaje) {
            $this->assertDoesNotMatchRegularExpression('/\b(field|required|must|valid)\b/i', $mensaje);
        }
    }

    public function test_ca11_correo_invalido(): void
    {
        $this->crear($this->nuevo(['nit' => '310', 'correo' => 'no-es-correo']))
            ->assertSessionHasErrors(['correo' => 'Escriba un correo válido.'])
            ->assertSessionHasInput('nombre', 'Paciente Prueba')
            ->assertSessionHasInput('nit', '310')
            ->assertSessionHasInput('telefono', '7761-9999');
    }

    public function test_ca11_correo_valido_se_guarda(): void
    {
        $this->crear($this->nuevo(['correo' => 'paciente@prueba.test']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', ['telefono' => '77619999', 'correo' => 'paciente@prueba.test']);
    }

    public function test_ca11_nombre_y_correo_largos(): void
    {
        $this->crear($this->nuevo([
            'nombre' => str_repeat('a', 151),
            'correo' => str_repeat('a', 140).'@prueba.test',
        ]))->assertSessionHasErrors([
            'nombre' => 'El nombre no puede pasar de 150 caracteres.',
            'correo' => 'El correo no puede pasar de 150 caracteres.',
        ]);

        $this->crear($this->nuevo(['nombre' => str_repeat('a', 150)]))->assertSessionHasNoErrors();
    }

    /**
     * Desde el spec 07, vendedor_id es un campo válido del alta: ya no se ignora.
     * Lo que se prueba ahora es que un asesor de otro equipo, escrito a mano, se rechace.
     */
    public function test_ca11_vendedor_id_ajeno_a_mano_se_rechaza(): void
    {
        $carlos = $this->u('carlos@crm.test');

        $this->crear($this->nuevo(['vendedor_id' => $carlos->id]), $this->sergio)
            ->assertSessionHasErrors(['vendedor_id' => 'Elija un asesor activo de su equipo.']);

        $this->assertDatabaseMissing('clientes', ['telefono' => '77619999']);
    }

    public function test_formulario_de_alta(): void
    {
        $this->actingAs($this->alicia)->get('/clientes/create')
            ->assertOk()
            ->assertSee('Nuevo paciente')
            ->assertSee('Con su dígito verificador, por ejemplo 4521817-K, o CF.')
            ->assertSee('8 dígitos; puede incluir +502.')
            ->assertSee('name="vendedor_id"', false)     // asesor opcional al crear (spec 07)
            ->assertSee('Disponible (sin asesor)');
    }

    public function test_ca12_sergio_crea_un_paciente_disponible_y_lo_ve(): void
    {
        $this->crear($this->nuevo(), $this->sergio)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes', ['nombre' => 'Paciente Prueba', 'vendedor_id' => null]);
        $this->actingAs($this->sergio)->get('/clientes')->assertSee('12 pacientes');
    }

    // ---- Detalle y edición (paso 5) ----

    /** Datos del formulario de edición tal como vienen de la base. */
    private function datosDe(Cliente $c, array $cambios = []): array
    {
        return array_merge([
            'nombre' => $c->nombre,
            'nit' => $c->nitEnPantalla(),
            'telefono' => $c->telefono,
            'correo' => $c->correo ?? '',
        ], $cambios);
    }

    // CA6 · Lo ajeno da 404 por URL

    public function test_ca6_sergio_recibe_404_en_paciente_de_otro_equipo(): void
    {
        $abarrotes = $this->paciente('Abarrotes San Juan');

        $this->actingAs($this->sergio)->get('/clientes/'.$abarrotes->id)->assertNotFound();
        $this->actingAs($this->sergio)->get('/clientes/'.$abarrotes->id.'/edit')->assertNotFound();
        $this->actingAs($this->sergio)->put('/clientes/'.$abarrotes->id, ['nombre' => 'Cambiado'])->assertNotFound();
        $this->actingAs($this->sergio)->put('/clientes/'.$abarrotes->id, $this->datosDe($abarrotes, ['nombre' => 'Cambiado']))->assertNotFound();
        $this->actingAs($this->sergio)->patch('/clientes/'.$abarrotes->id, ['nombre' => 'Cambiado'])->assertNotFound();

        $this->assertSame('Abarrotes San Juan', $abarrotes->fresh()->nombre);
    }

    public function test_ca6_id_inexistente_da_404(): void
    {
        $this->actingAs($this->alicia)->get('/clientes/999999')->assertNotFound();
        $this->actingAs($this->alicia)->get('/clientes/999999/edit')->assertNotFound();
        $this->actingAs($this->alicia)->put('/clientes/999999', $this->nuevo())->assertNotFound();
        $this->actingAs($this->alicia)->get('/clientes/abc')->assertNotFound();
    }

    // CA12 · El coordinador edita los de su equipo y los disponibles

    public function test_ca12_sergio_edita_disponible_y_de_su_equipo(): void
    {
        foreach (['Café Zunil', 'Farmacia Los Altos'] as $nombre) {
            $c = $this->paciente($nombre);
            $vendedorAntes = $c->vendedor_id;

            $this->actingAs($this->sergio)->get('/clientes/'.$c->id.'/edit')->assertOk()->assertSee('Editar paciente');
            $this->actingAs($this->sergio)
                ->put('/clientes/'.$c->id, $this->datosDe($c, ['nombre' => $nombre.' (editado)']))
                ->assertRedirect(route('clientes.show', $c->id))
                ->assertSessionHas('status', 'El paciente se actualizó correctamente.');

            $c->refresh();
            $this->assertSame($nombre.' (editado)', $c->nombre);
            $this->assertSame($vendedorAntes, $c->vendedor_id);
        }
    }

    public function test_store_lleva_al_detalle(): void
    {
        $r = $this->crear($this->nuevo());
        $creado = Cliente::where('nombre', 'Paciente Prueba')->firstOrFail();
        $r->assertRedirect(route('clientes.show', $creado->id));
    }

    // CA13 · Editar sin chocar consigo mismo

    public function test_ca13_editar_con_sus_propios_datos_escritos_de_otra_forma(): void
    {
        $quetzal = $this->paciente('Comercial El Quetzal');

        $this->actingAs($this->alicia)
            ->put('/clientes/'.$quetzal->id, $this->datosDe($quetzal, ['nit' => '4521817-k', 'telefono' => '5555 1234']))
            ->assertSessionHasNoErrors();

        $quetzal->refresh();
        $this->assertSame('4521817K', $quetzal->nit);
        $this->assertSame('55551234', $quetzal->telefono);
    }

    public function test_ca13_telefono_de_otro_paciente(): void
    {
        $quetzal = $this->paciente('Comercial El Quetzal');

        $this->actingAs($this->alicia)
            ->from('/clientes/'.$quetzal->id.'/edit')
            ->put('/clientes/'.$quetzal->id, $this->datosDe($quetzal, ['telefono' => '77612345']))
            ->assertRedirect('/clientes/'.$quetzal->id.'/edit')
            ->assertSessionHasErrors(['telefono' => 'Ya existe un paciente con ese teléfono.']);

        $this->assertSame('55551234', $quetzal->fresh()->telefono);
    }

    public function test_editar_muestra_cf_y_vacia_el_correo(): void
    {
        $quetzal = $this->paciente('Comercial El Quetzal');
        $coyoy = $this->paciente('María Coyoy');

        $this->actingAs($this->alicia)->get('/clientes/'.$coyoy->id.'/edit')->assertSee('value="CF"', false);

        $this->actingAs($this->alicia)->put('/clientes/'.$quetzal->id, $this->datosDe($quetzal, ['correo' => '', 'nit' => 'cf']))
            ->assertSessionHasNoErrors();
        $quetzal->refresh();
        $this->assertNull($quetzal->correo);
        $this->assertNull($quetzal->nit);
    }

    // CA14 · Detalle

    public function test_ca14_detalle_con_sus_planes(): void
    {
        $quetzal = $this->paciente('Comercial El Quetzal');

        $this->actingAs($this->alicia)->get('/clientes/'.$quetzal->id)
            ->assertOk()
            ->assertSeeInOrder(['Comercial El Quetzal', '4521817K', '55551234', 'compras@elquetzal.test', 'Valeria López'])
            ->assertSeeInOrder(['Renovación de planes', 'Aceptado', 'Valeria López'])
            ->assertSeeInOrder(['Ampliación a bodega', 'En negociación', 'Valeria López'])
            ->assertSeeInOrder(['Seguimiento anual', 'Interesado', 'Valeria López'])
            ->assertDontSee('Q5,650.00');
    }

    public function test_ca14_detalle_de_un_disponible_sin_planes(): void
    {
        $zunil = $this->paciente('Café Zunil');

        $this->actingAs($this->sergio)->get('/clientes/'.$zunil->id)
            ->assertOk()
            ->assertSeeInOrder(['CF', '30002002', 'Disponible'])
            ->assertSee('Este paciente no tiene planes de tratamiento.');
    }

    // CA15 · Carrera: el 1062 vuelve junto al campo

    public function test_ca15_nit_repetido_que_se_cuela_al_crear(): void
    {
        // Justo antes del INSERT, "otra petición" guarda el mismo NIT.
        Cliente::creating(function () {
            DB::table('clientes')->insert(['nombre' => 'Se coló', 'nit' => '310', 'telefono' => '29999998']);
        });

        $this->crear($this->nuevo(['nit' => '3-10']))
            ->assertRedirect('/clientes/create')
            ->assertSessionHasErrors(['nit' => 'Ya existe un paciente con ese NIT.'])
            ->assertSessionHasInput('nit', '3-10');

        $this->assertDatabaseMissing('clientes', ['nombre' => 'Paciente Prueba']);
    }

    public function test_ca15_telefono_repetido_que_se_cuela_al_editar(): void
    {
        $zunil = $this->paciente('Café Zunil');

        Cliente::updating(function () {
            DB::table('clientes')->insert(['nombre' => 'Se coló', 'nit' => null, 'telefono' => '29999997']);
        });

        $this->actingAs($this->alicia)
            ->from('/clientes/'.$zunil->id.'/edit')
            ->put('/clientes/'.$zunil->id, $this->datosDe($zunil, ['telefono' => '2999-9997']))
            ->assertRedirect('/clientes/'.$zunil->id.'/edit')
            ->assertSessionHasErrors(['telefono' => 'Ya existe un paciente con ese teléfono.'])
            ->assertSessionHasInput('telefono', '2999-9997');

        $this->assertSame('30002002', $zunil->fresh()->telefono);
    }

    // ---- Eliminar (paso 6) ----

    // CA16 · Eliminar

    public function test_ca16_alicia_elimina_cafe_zunil_y_sus_actividades(): void
    {
        $zunil = $this->paciente('Café Zunil');
        DB::table('actividades')->insert([
            'cliente_id' => $zunil->id,
            'vendedor_id' => $this->u('valeria@crm.test')->id,
            'tipo' => 'llamada',
            'descripcion' => 'Llamada de prueba',
        ]);

        $this->actingAs($this->alicia)->delete('/clientes/'.$zunil->id)
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('status', 'El paciente se eliminó.');

        $this->assertDatabaseMissing('clientes', ['id' => $zunil->id]);
        $this->assertDatabaseMissing('actividades', ['cliente_id' => $zunil->id]);
        $this->assertSame(5, Cliente::whereNull('vendedor_id')->count());

        $this->actingAs($this->alicia)->get('/clientes')
            ->assertSee('20 pacientes')
            ->assertSee('El paciente se eliminó.');
    }

    public function test_ca16_no_se_elimina_con_planes(): void
    {
        $xela = $this->paciente('Ferretería Xela');

        $this->actingAs($this->alicia)->delete('/clientes/'.$xela->id)
            ->assertRedirect(route('clientes.show', $xela->id))
            ->assertSessionHas('error', 'No se puede eliminar: el paciente tiene planes de tratamiento.');

        $this->assertDatabaseHas('clientes', ['id' => $xela->id]);

        $this->actingAs($this->alicia)->get('/clientes/'.$xela->id)
            ->assertSee('No se puede eliminar: el paciente tiene planes de tratamiento.');
    }

    public function test_ca16_con_solo_planes_cerrados_tampoco_se_elimina(): void
    {
        // María Coyoy solo tiene planes cerrados (ganada y perdida).
        $coyoy = $this->paciente('María Coyoy');

        $this->actingAs($this->alicia)->delete('/clientes/'.$coyoy->id)
            ->assertSessionHas('error', 'No se puede eliminar: el paciente tiene planes de tratamiento.');
        $this->assertDatabaseHas('clientes', ['id' => $coyoy->id]);
    }

    public function test_ca16_plan_creado_en_carrera_da_el_mismo_mensaje(): void
    {
        $zunil = $this->paciente('Café Zunil');

        // Justo antes del DELETE, "la API" le crea un plan: la FK responde 1451.
        Cliente::deleting(function (Cliente $c) {
            DB::table('oportunidades')->insert([
                'cliente_id' => $c->id,
                'vendedor_id' => $this->u('valeria@crm.test')->id,
                'titulo' => 'Plan que se coló',
                'etapa' => 'prospecto',
            ]);
        });

        $this->actingAs($this->alicia)->delete('/clientes/'.$zunil->id)
            ->assertRedirect(route('clientes.show', $zunil->id))
            ->assertSessionHas('error', 'No se puede eliminar: el paciente tiene planes de tratamiento.');

        $this->assertDatabaseHas('clientes', ['id' => $zunil->id]);
    }

    public function test_ca16_id_inexistente_da_404(): void
    {
        $this->actingAs($this->alicia)->delete('/clientes/999999')->assertNotFound();
    }

    // CA14 · El botón Eliminar solo para el administrador

    public function test_ca14_solo_alicia_ve_eliminar(): void
    {
        $quetzal = $this->paciente('Comercial El Quetzal');

        $this->actingAs($this->alicia)->get('/clientes/'.$quetzal->id)
            ->assertSee('Eliminar')
            ->assertSee('action="'.route('clientes.destroy', $quetzal->id).'"', false);

        $this->actingAs($this->sergio)->get('/clientes/'.$quetzal->id)
            ->assertOk()
            ->assertDontSee('Eliminar');
    }

    // CA17 · El coordinador no elimina

    public function test_ca17_sergio_recibe_403_al_eliminar(): void
    {
        $zunil = $this->paciente('Café Zunil');

        $this->actingAs($this->sergio)->delete('/clientes/'.$zunil->id)->assertForbidden();
        $this->assertDatabaseHas('clientes', ['id' => $zunil->id]);
    }

    public function test_ca17_la_ruta_delete_solo_lleva_rol_administrador(): void
    {
        $ruta = app('router')->getRoutes()->getByName('clientes.destroy');

        $this->assertSame(['DELETE'], $ruta->methods());
        $this->assertContains('rol:administrador', $ruta->gatherMiddleware());
        $this->assertNotContains('rol:administrador,supervisor', $ruta->gatherMiddleware());
    }

    // CA18 · Menú y entrada

    public function test_ca18_menu_y_dashboard_llevan_a_pacientes(): void
    {
        foreach ([$this->alicia, $this->sergio] as $quien) {
            $this->actingAs($quien)->get('/dashboard')->assertRedirect(route('clientes.index'));
            $this->actingAs($quien)->get('/profile')
                ->assertSee('href="'.route('clientes.index').'"', false)
                ->assertSee('Pacientes');
        }
    }

    // CA19 · XSS

    public function test_ca19_el_nombre_se_muestra_literal(): void
    {
        $nombre = '<img src=x onerror=alert(1)>';
        $this->crear($this->nuevo(['nombre' => $nombre]))->assertSessionHasNoErrors();
        $creado = Cliente::where('nombre', $nombre)->firstOrFail();

        foreach (['/clientes', '/clientes/'.$creado->id, '/clientes/'.$creado->id.'/edit'] as $url) {
            $this->actingAs($this->alicia)->get($url)
                ->assertOk()
                ->assertDontSee($nombre, false)
                ->assertSee(e($nombre), false);
        }
    }
}
