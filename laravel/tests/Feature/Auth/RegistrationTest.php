<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // Pendiente del spec 03 (cuentas y roles): no hay registro público.
        // El registro de Breeze crea un vendedor sin supervisor y la CHECK
        // chk_users_supervisor lo rechaza. El spec 03 quita esta ruta y esta prueba.
        $this->markTestIncomplete('Pendiente del spec 03: se quita el registro público.');

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
