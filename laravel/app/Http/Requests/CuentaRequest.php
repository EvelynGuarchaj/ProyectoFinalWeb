<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de cuentas desde el panel (L2, spec 03 sección 2.5).
 * La ruta ya pasa por rol:administrador.
 */
class CuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Cuenta que se edita; null al crear. */
    private function cuenta(): ?User
    {
        return $this->route('cuenta');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cuenta = $this->cuenta();

        $supervisor = [
            'exclude_unless:rol,vendedor',
            'required',
            'integer',
            Rule::exists('users', 'id')->where('rol', 'supervisor')->where('activo', 1),
        ];
        if ($cuenta) {
            // Un coordinador sin asesores que pasa a Asesor no puede ser su propio coordinador.
            $supervisor[] = Rule::notIn([$cuenta->id]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($cuenta)],
            'password' => [$cuenta ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'rol' => ['required', Rule::in(array_keys(User::ROLES))],
            'supervisor_id' => $supervisor,
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Escriba el nombre.',
            'name.string' => 'Escriba el nombre.',
            'name.max' => 'El nombre no puede pasar de 255 caracteres.',
            'email.required' => 'Escriba el correo.',
            'email.string' => 'Escriba un correo válido.',
            'email.email' => 'Escriba un correo válido.',
            'email.max' => 'Escriba un correo válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.required' => 'Escriba la contraseña.',
            'password.string' => 'Escriba la contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'rol.required' => 'Elija el rol.',
            'rol.in' => 'Elija un rol de la lista.',
            'supervisor_id.required' => 'Elija el coordinador del asesor.',
            'supervisor_id.integer' => 'Elija un coordinador activo de la lista.',
            'supervisor_id.exists' => 'Elija un coordinador activo de la lista.',
            'supervisor_id.not_in' => 'Elija un coordinador activo de la lista.',
            'activo.required' => 'Elija el estado.',
            'activo.boolean' => 'Elija el estado.',
        ];
    }

    /**
     * Reglas que no caben en una regla simple. Solo al editar.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $cuenta = $this->cuenta();
                $errores = $validator->errors();

                if (! $cuenta || $errores->has('rol') || $errores->has('activo')) {
                    return;
                }

                $desactiva = ! $this->boolean('activo');
                $cambiaRol = $this->input('rol') !== $cuenta->rol;

                if ($cuenta->is($this->user())) {
                    if ($desactiva) {
                        $errores->add('activo', 'No puede desactivar su propia cuenta.');
                    }
                    if ($cambiaRol) {
                        $errores->add('rol', 'No puede cambiar su propio rol.');
                    }

                    return;
                }

                if ($cuenta->esSupervisor() && ($desactiva || $cambiaRol) && $cuenta->vendedores()->exists()) {
                    if ($desactiva) {
                        $errores->add('activo', 'No puede desactivar a un coordinador que tiene asesores. Primero cámbielos de coordinador.');
                    }
                    if ($cambiaRol) {
                        $errores->add('rol', 'No puede cambiar el rol de un coordinador que tiene asesores. Primero cámbielos de coordinador.');
                    }
                }

                if ($cuenta->esVendedor() && $cambiaRol && $cuenta->clientes()->exists()) {
                    $errores->add('rol', 'No puede cambiar el rol de un asesor que tiene pacientes. Primero reasígnelos o libérelos.');
                }
            },
        ];
    }
}
