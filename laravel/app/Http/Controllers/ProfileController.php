<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil de la cuenta con sesión. Solo muestra el cambio de contraseña:
 * el nombre, el correo y el estado los cambia el administrador (spec 03).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }
}
