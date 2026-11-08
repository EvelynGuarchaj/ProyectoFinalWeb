<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página "Mi equipo" del coordinador (spec 09, L5): cartera y ventas del mes
 * de cada asesor de su equipo, activos y desactivados.
 */
class EquipoController extends Controller
{
    private const PATRON_MES = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public const MES_NO_VALIDO = 'Elija un mes válido (AAAA-MM).';

    public function index(Request $request): View
    {
        [$mes, $avisoMes] = $this->mesElegido($request->query('mes'));

        $asesores = User::query()
            ->where('rol', 'vendedor')
            ->where('supervisor_id', $request->user()->id)
            ->withCount('clientes as cartera')
            ->orderBy('name')
            ->get(['id', 'name', 'activo']);

        return view('equipo.index', compact('mes', 'avisoMes', 'asesores'));
    }

    /**
     * Mes de la consulta (sección 2.1 del spec): ausente o vacío → mes actual
     * de Guatemala; con formato AAAA-MM → ese mes; cualquier otra cosa → mes
     * actual con aviso. Devuelve [mes, aviso o null].
     */
    private function mesElegido(mixed $valor): array
    {
        $actual = now('America/Guatemala')->format('Y-m');

        if ($valor === null || $valor === '') {
            return [$actual, null];
        }

        if (is_string($valor) && preg_match(self::PATRON_MES, $valor) === 1) {
            return [$valor, null];
        }

        return [$actual, self::MES_NO_VALIDO];
    }
}
