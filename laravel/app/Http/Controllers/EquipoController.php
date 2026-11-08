<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reporte;
use App\Support\Mes;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página "Mi equipo" del coordinador (spec 09, L5): cartera y ventas del mes
 * de cada asesor de su equipo, activos y desactivados. Las ventas salen de
 * Reporte::ventasDelMes(), la misma consulta del reporte del mes (spec 14).
 */
class EquipoController extends Controller
{
    public function index(Request $request, Reporte $reporte): View
    {
        [$mes, $avisoMes] = Mes::elegido($request->query('mes'));

        $asesores = User::query()
            ->select('users.id', 'users.name', 'users.activo')
            ->selectRaw('COALESCE(v.ventas, 0) AS ventas, COALESCE(v.total, 0) AS total')
            ->leftJoinSub($reporte->ventasDelMes($mes), 'v', 'v.vendedor_id', '=', 'users.id')
            ->where('users.rol', 'vendedor')
            ->where('users.supervisor_id', $request->user()->id)
            ->withCount('clientes as cartera')
            ->orderBy('users.name')
            ->get();

        return view('equipo.index', compact('mes', 'avisoMes', 'asesores'));
    }
}
