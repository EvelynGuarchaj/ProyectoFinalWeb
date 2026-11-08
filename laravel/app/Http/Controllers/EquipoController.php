<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Mes;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Página "Mi equipo" del coordinador (spec 09, L5): cartera y ventas del mes
 * de cada asesor de su equipo, activos y desactivados.
 */
class EquipoController extends Controller
{
    public function index(Request $request): View
    {
        [$mes, $avisoMes] = Mes::elegido($request->query('mes'));

        $asesores = User::query()
            ->select('users.id', 'users.name', 'users.activo')
            ->selectRaw('COALESCE(v.ventas, 0) AS ventas, COALESCE(v.total, 0) AS total')
            ->leftJoinSub($this->ventasDelMes($mes), 'v', 'v.vendedor_id', '=', 'users.id')
            ->where('users.rol', 'vendedor')
            ->where('users.supervisor_id', $request->user()->id)
            ->withCount('clientes as cartera')
            ->orderBy('users.name')
            ->get();

        return view('equipo.index', compact('mes', 'avisoMes', 'asesores'));
    }

    /**
     * Ventas del mes por asesor (R7): planes ganados cuyo cierre, en hora de
     * Guatemala, cae en el mes. El mes va como parámetro. El crédito es de
     * oportunidades.vendedor_id. LEFT JOIN + COUNT(DISTINCT) cuenta cada venta
     * una vez aunque tenga varias líneas. El total es DECIMAL calculado en MySQL.
     */
    private function ventasDelMes(string $mes): Builder
    {
        return DB::table('oportunidades as o')
            ->leftJoin('oportunidad_productos as l', 'l.oportunidad_id', '=', 'o.id')
            ->where('o.etapa', 'ganada')
            ->whereRaw("DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?", [$mes])
            ->groupBy('o.vendedor_id')
            ->selectRaw('o.vendedor_id, COUNT(DISTINCT o.id) AS ventas, SUM(l.cantidad * l.precio_unitario) AS total');
    }
}
