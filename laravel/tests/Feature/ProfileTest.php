<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 03, CA2: el perfil solo cambia la contraseña.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_de_perfil_se_muestra(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('update_password_current_password', false)
            ->assertDontSee('send-verification', false)
            ->assertDontSee('confirm-user-deletion', false);
    }

    public function test_no_se_puede_editar_ni_borrar_el_perfil(): void
    {
        $user = User::factory()->create(['name' => 'Nombre original']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => 'Otro', 'email' => 'otro@example.com'])
            ->assertStatus(405);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertStatus(405);

        $user->refresh();
        $this->assertSame('Nombre original', $user->name);
        $this->assertNotSame('otro@example.com', $user->email);
    }
}
