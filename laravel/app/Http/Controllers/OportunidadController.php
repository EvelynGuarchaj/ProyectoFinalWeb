<?php

namespace App\Http\Controllers;

use App\Models\Oportunidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Planes de tratamiento (L6, parte 1). Solo lectura, administrador y coordinador.
 * Sin enlace implícito de modelo: cada acción busca con
 * Oportunidad::visiblePara($usuario), así lo ajeno responde 404 (S5).
 * El total sale de MySQL con Oportunidad::conTotal(); PHP no hace cuentas con dinero.
 */
class OportunidadController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        // Filtros no válidos se ignoran sin aviso (spec 12, sección 2.3).
        $etapa = $request->query('etapa');
        if (! is_string($etapa) || ! in_array($etapa, Oportunidad::ETAPAS, true)) {
            $etapa = '';
        }

        $asesor = $request->query('asesor');
        if (! is_string($asesor) || ! ctype_digit($asesor)) {
            $asesor = '';
        }

        $planes = Oportunidad::visiblePara($usuario)
            ->conTotal()
            ->with(['cliente:id,nombre', 'vendedor:id,name'])
            ->when($etapa !== '', fn (Builder $q) => $q->where('etapa', $etapa))
            ->when($asesor !== '', fn (Builder $q) => $q->where('vendedor_id', (int) $asesor))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('oportunidades.index', [
            'planes' => $planes,
            'asesores' => User::asesoresVisiblesPara($usuario)->orderBy('name')->get(['id', 'name', 'activo']),
            'etapa' => $etapa,
            'asesor' => $asesor,
        ]);
    }

    /**
     * Detalle: datos, líneas con el precio guardado y su subtotal (en SQL), total
     * e historial del más antiguo al más reciente. Lo ajeno o inexistente → 404.
     */
    public function show(Request $request, string $oportunidad): View
    {
        $plan = Oportunidad::visiblePara($request->user())
            ->conTotal()
            ->with(['cliente:id,nombre', 'vendedor:id,name'])
            ->findOrFail($oportunidad);

        $lineas = $plan->lineas()
            ->with('producto:id,nombre')
            ->select('oportunidad_productos.*')
            ->selectRaw('cantidad * precio_unitario AS subtotal')
            ->orderBy('id')
            ->get();

        $historial = $plan->historial()
            ->with('usuario:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('oportunidades.show', [
            'plan' => $plan,
            'lineas' => $lineas,
            'historial' => $historial,
        ]);
    }
}
