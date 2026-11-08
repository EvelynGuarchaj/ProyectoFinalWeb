<?php

namespace App\Http\Controllers;

use App\Exceptions\CarteraException;
use App\Http\Requests\AsignarRequest;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Cartera;
use App\Support\Nit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Pacientes (L4, parte 1). Administrador y coordinador.
 * Sin enlace implícito de modelo: cada acción busca con
 * Cliente::visiblePara($usuario)->findOrFail($id), así lo ajeno responde 404 (S5).
 */
class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';

        $asesor = $request->query('asesor');
        if (! is_string($asesor) || ($asesor !== 'sin' && ! ctype_digit($asesor))) {
            $asesor = '';
        }

        $clientes = Cliente::visiblePara($usuario)
            ->with('vendedor:id,name,activo')
            ->when($q !== '', function (Builder $consulta) use ($q) {
                $nit = Nit::normalizar($q);
                $consulta->where(function (Builder $w) use ($q, $nit) {
                    $w->where('nombre', 'like', '%'.$this->escaparLike($q).'%');
                    if ($nit !== '') {
                        $w->orWhere('nit', 'like', '%'.$this->escaparLike($nit).'%');
                    }
                });
            })
            ->when($asesor === 'sin', fn (Builder $c) => $c->whereNull('vendedor_id'))
            ->when(ctype_digit($asesor), fn (Builder $c) => $c->where('vendedor_id', (int) $asesor))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('clientes.index', [
            'clientes' => $clientes,
            'asesores' => $this->asesoresVisibles($usuario),
            'asignables' => $this->asesoresAsignables($usuario),
            'q' => $q,
            'asesor' => $asesor,
        ]);
    }

    public function create(Request $request): View
    {
        return view('clientes.create', [
            'asignables' => $this->asesoresAsignables($request->user()),
        ]);
    }

    /**
     * Alta con asesor opcional (spec 07): Cartera::crear() bloquea al asesor,
     * cuenta su cartera y crea el paciente en la misma transacción.
     */
    public function store(ClienteRequest $request, Cartera $cartera): RedirectResponse
    {
        $vendedorId = $request->validated('vendedor_id');
        $vendedor = $vendedorId === null ? null : User::findOrFail($vendedorId);

        try {
            $cliente = $cartera->crear($request->datos(), $vendedor, $request->user());
        } catch (CarteraException $e) {
            return back()->withInput()->withErrors([$e->campo ?? 'vendedor_id' => $e->getMessage()]);
        } catch (QueryException $e) {
            return $this->repetidoPorCarrera($e);
        }

        return redirect()->route('clientes.show', $cliente->id)->with('status', 'El paciente se creó correctamente.');
    }

    public function show(Request $request, string $cliente): View
    {
        $cliente = Cliente::visiblePara($request->user())
            ->with('vendedor:id,name')
            ->findOrFail($cliente);

        $planes = $cliente->oportunidades()
            ->with('vendedor:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'titulo', 'etapa', 'vendedor_id', 'cliente_id']);

        return view('clientes.show', [
            'cliente' => $cliente,
            'planes' => $planes,
            'asignables' => $this->asesoresAsignables($request->user()),
        ]);
    }

    public function edit(Request $request, string $cliente): View
    {
        return view('clientes.edit', [
            'cliente' => Cliente::visiblePara($request->user())->findOrFail($cliente),
        ]);
    }

    /** No toca vendedor_id: asignar, reasignar y liberar son del spec 07. */
    public function update(ClienteRequest $request, string $cliente): RedirectResponse
    {
        $cliente = Cliente::visiblePara($request->user())->findOrFail($cliente);

        try {
            $cliente->update($request->datos());
        } catch (QueryException $e) {
            return $this->repetidoPorCarrera($e);
        }

        return redirect()->route('clientes.show', $cliente->id)->with('status', 'El paciente se actualizó correctamente.');
    }

    /**
     * Solo el administrador (rol:administrador) y solo si el paciente no tiene
     * ningún plan, abierto o cerrado. Sus actividades se borran en cascada.
     */
    public function destroy(Request $request, string $cliente): RedirectResponse
    {
        $cliente = Cliente::visiblePara($request->user())->findOrFail($cliente);
        $conPlanes = 'No se puede eliminar: el paciente tiene planes de tratamiento.';

        if ($cliente->oportunidades()->exists()) {
            return redirect()->route('clientes.show', $cliente->id)->with('error', $conPlanes);
        }

        try {
            $cliente->delete();
        } catch (QueryException $e) {
            // Alguien le creó un plan entre la comprobación y el DELETE: la FK lo impide (1451).
            if (($e->errorInfo[1] ?? null) === 1451) {
                return redirect()->route('clientes.show', $cliente->id)->with('error', $conPlanes);
            }
            throw $e;
        }

        return redirect()->route('clientes.index')->with('status', 'El paciente se eliminó.');
    }

    /**
     * Asigna un paciente disponible o lo reasigna (spec 07). AsignarRequest ya
     * respondió 404 si el paciente es ajeno y validó el asesor; Cartera lo vuelve
     * a comprobar con el candado puesto.
     */
    public function asignar(AsignarRequest $request, Cartera $cartera, string $cliente): RedirectResponse
    {
        $cliente = Cliente::visiblePara($request->user())->findOrFail($cliente);
        $vendedor = User::findOrFail($request->validated('vendedor_id'));

        try {
            $cartera->asignar($cliente, $vendedor, $request->user());
        } catch (CarteraException $e) {
            return back()->withErrors([$e->campo ?? 'vendedor_id' => $e->getMessage()], 'asignar'.$cliente->id);
        }

        return back()->with('status', 'El paciente se asignó correctamente.');
    }

    /** Deja disponible al paciente (spec 07). Con planes abiertos, mensaje de página. */
    public function liberar(Request $request, Cartera $cartera, string $cliente): RedirectResponse
    {
        $cliente = Cliente::visiblePara($request->user())->findOrFail($cliente);

        try {
            $cartera->liberar($cliente, $request->user());
        } catch (CarteraException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'El paciente quedó disponible.');
    }

    /**
     * Un repetido que se coló entre la validación y la escritura: el índice único
     * responde 1062 y el error vuelve junto a su campo, nunca como página de error.
     */
    private function repetidoPorCarrera(QueryException $e): RedirectResponse
    {
        if (($e->errorInfo[1] ?? null) === 1062) {
            $mensaje = $e->getMessage();
            if (str_contains($mensaje, 'clientes_nit_unique')) {
                return back()->withInput()->withErrors(['nit' => 'Ya existe un paciente con ese NIT.']);
            }
            if (str_contains($mensaje, 'clientes_telefono_unique')) {
                return back()->withInput()->withErrors(['telefono' => 'Ya existe un paciente con ese teléfono.']);
            }
        }

        throw $e;
    }

    /** Asesores para el filtro: todos para el administrador; los de su equipo para el coordinador. */
    private function asesoresVisibles(User $usuario): Collection
    {
        return User::where('rol', 'vendedor')
            ->when(! $usuario->esAdmin(), fn ($c) => $c->where('supervisor_id', $usuario->id))
            ->orderBy('name')
            ->get(['id', 'name', 'activo']);
    }

    /**
     * Asesores a los que la persona puede asignar, con su cartera (clientes_count)
     * para mostrar "x de 10": activos y, para el coordinador, de su equipo.
     */
    private function asesoresAsignables(User $usuario): Collection
    {
        return User::where('rol', 'vendedor')
            ->where('activo', 1)
            ->when($usuario->esSupervisor(), fn ($c) => $c->where('supervisor_id', $usuario->id))
            ->withCount('clientes')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Escapa los comodines de LIKE (\, % y _) para buscar el texto literal. */
    private function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
    }
}
