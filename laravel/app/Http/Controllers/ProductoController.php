<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoRequest;
use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Catálogo de tratamientos (L3). Solo el administrador (rol:administrador).
 * No hay show ni destroy: los tratamientos no se borran, se desactivan.
 * Cambiar un precio no toca oportunidad_productos (cada línea guarda su precio).
 */
class ProductoController extends Controller
{
    private const NOMBRE_REPETIDO = 'Ya existe un tratamiento con ese nombre.';

    public function index(): View
    {
        return view('productos.index', [
            'productos' => Producto::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('productos.create');
    }

    public function store(ProductoRequest $request): RedirectResponse
    {
        try {
            Producto::create($request->validated());
        } catch (QueryException $e) {
            return $this->nombreRepetido($e);
        }

        return redirect()->route('productos.index')
            ->with('status', 'El tratamiento se creó correctamente.');
    }

    public function edit(Producto $producto): View
    {
        return view('productos.edit', ['producto' => $producto]);
    }

    public function update(ProductoRequest $request, Producto $producto): RedirectResponse
    {
        try {
            $producto->update($request->validated());
        } catch (QueryException $e) {
            return $this->nombreRepetido($e);
        }

        return redirect()->route('productos.index')
            ->with('status', 'El tratamiento se actualizó correctamente.');
    }

    /**
     * Activa o desactiva. Un tratamiento desactivado no se borra: las líneas
     * que ya lo tienen no cambian.
     */
    public function cambiarEstado(Producto $producto): RedirectResponse
    {
        $producto->update(['activo' => ! $producto->activo]);

        return redirect()->route('productos.index')->with('status', $producto->activo
            ? 'El tratamiento se activó.'
            : 'El tratamiento se desactivó.');
    }

    /**
     * El índice único es el árbitro final: si otra escritura ganó la carrera
     * después de la regla unique, MySQL responde 1062 y se muestra junto al nombre.
     * Cualquier otro error de la base se vuelve a lanzar.
     */
    private function nombreRepetido(QueryException $e): RedirectResponse
    {
        if (($e->errorInfo[1] ?? null) !== 1062) {
            throw $e;
        }

        return back()->withInput()->withErrors(['nombre' => self::NOMBRE_REPETIDO]);
    }
}
