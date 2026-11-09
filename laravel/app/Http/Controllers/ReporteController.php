<?php

namespace App\Http\Controllers;

use App\Services\Reporte;
use App\Support\Mes;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reporte del mes (spec 14, L6): por asesor, por equipo (solo administrador) y
 * por tratamiento. El administrador ve a todos; el coordinador, a su equipo.
 * Todas las cifras salen de MySQL (App\Services\Reporte).
 */
class ReporteController extends Controller
{
    public function index(Request $request, Reporte $reporte): View
    {
        [$mes, $avisoMes] = Mes::elegido($request->query('mes'));
        $quien = $request->user();

        return view('reportes.index', [
            'mes' => $mes,
            'avisoMes' => $avisoMes,
            'esAdmin' => $quien->esAdmin(),
            'asesores' => $reporte->porAsesor($quien, $mes),
            'totales' => $reporte->totales($quien, $mes),
            'equipos' => $quien->esAdmin() ? $reporte->porEquipo($mes) : collect(),
            'tratamientos' => $reporte->porTratamiento($quien, $mes),
            'totalTratamientos' => $reporte->totalPorTratamiento($quien, $mes),
        ]);
    }
}
