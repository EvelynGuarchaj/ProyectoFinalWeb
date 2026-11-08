<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta del panel (alias 'panel', va después de 'auth').
 *
 * La sesión guarda al usuario de cuando entró; por eso se vuelve a leer de la
 * base en cada petición. Si la cuenta ya no existe, está desactivada o pasó a
 * ser asesor, se cierra la sesión y vuelve al login (L2, sección 1.2).
 */
class CuentaPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user()->fresh();

        if (! $usuario || ! $usuario->activo || $usuario->esVendedor()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Su cuenta está desactivada. Comuníquese con el administrador.',
            ]);
        }

        return $next($request);
    }
}
