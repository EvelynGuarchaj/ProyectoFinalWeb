<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas por rol (alias 'rol'). Uso: rol:administrador o
 * rol:administrador,supervisor. Una sección que el rol no usa responde 403
 * (distinto de lo ajeno, que responde 404).
 */
class Rol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()->rol, $roles, true), 403);

        return $next($request);
    }
}
