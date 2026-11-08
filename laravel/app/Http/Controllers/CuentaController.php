<?php

namespace App\Http\Controllers;

use App\Http\Requests\CuentaRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cuentas del sistema (L2). Solo el administrador (rol:administrador).
 * No hay show ni destroy: las cuentas no se borran, se desactivan.
 */
class CuentaController extends Controller
{
    public function index(Request $request): View
    {
        $rol = $request->query('rol');
        if (! is_string($rol) || ! array_key_exists($rol, User::ROLES)) {
            $rol = null;
        }

        $cuentas = User::with('supervisor')
            ->when($rol, fn ($consulta) => $consulta->where('rol', $rol))
            ->orderBy('name')
            ->get();

        return view('cuentas.index', [
            'cuentas' => $cuentas,
            'rol' => $rol,
        ]);
    }

    public function create(): View
    {
        return view('cuentas.create', [
            'supervisores' => $this->supervisores(null),
        ]);
    }

    public function store(CuentaRequest $request): RedirectResponse
    {
        User::create($this->datos($request));

        return redirect()->route('cuentas.index')->with('status', 'La cuenta se creó correctamente.');
    }

    public function edit(User $cuenta): View
    {
        return view('cuentas.edit', [
            'cuenta' => $cuenta,
            'supervisores' => $this->supervisores($cuenta),
        ]);
    }

    public function update(CuentaRequest $request, User $cuenta): RedirectResponse
    {
        $datos = $this->datos($request);

        // Contraseña vacía al editar: se conserva el hash actual.
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        $cuenta->update($datos);

        return redirect()->route('cuentas.index')->with('status', 'La cuenta se actualizó correctamente.');
    }

    /**
     * Datos validados. Si el rol no es vendedor, supervisor_id se guarda vacío
     * aunque el formulario traiga un valor (chk_users_supervisor lo exige).
     *
     * @return array<string, mixed>
     */
    private function datos(CuentaRequest $request): array
    {
        $datos = $request->validated();

        if ($datos['rol'] !== 'vendedor') {
            $datos['supervisor_id'] = null;
        }

        return $datos;
    }

    /** Coordinadores activos para el selector, sin la cuenta que se edita. */
    private function supervisores(?User $cuenta)
    {
        return User::where('rol', 'supervisor')
            ->where('activo', 1)
            ->when($cuenta, fn ($consulta) => $consulta->whereKeyNot($cuenta->id))
            ->orderBy('name')
            ->get();
    }
}
