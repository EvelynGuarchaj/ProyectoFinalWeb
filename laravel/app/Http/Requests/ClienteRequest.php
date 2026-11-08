<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Rules\NitValido;
use App\Rules\TelefonoValido;
use App\Support\Nit;
use App\Support\Telefono;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de pacientes (L4 parte 1, spec 05 sección 2.4).
 * La ruta ya pasa por rol:administrador,supervisor y el controlador busca con visiblePara.
 *
 * NIT y teléfono no se reemplazan antes de validar, para que old() devuelva
 * exactamente lo que la persona escribió. Se normalizan en after() y en datos().
 */
class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Al editar, lo ajeno responde 404 antes de validar (S5): si no, un PUT a un
     * paciente de otro equipo devolvería errores de validación (y podría confirmar
     * un NIT o teléfono ajeno) en lugar de 404.
     */
    protected function prepareForValidation(): void
    {
        $id = $this->route('cliente');
        if ($id !== null) {
            Cliente::visiblePara($this->user())->findOrFail($id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['bail', 'required', 'string', 'max:150'],
            'nit' => ['bail', 'required', 'string', new NitValido],
            'telefono' => ['bail', 'required', 'string', new TelefonoValido],
            'correo' => ['bail', 'nullable', 'string', 'email', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre del paciente.',
            'nombre.string' => 'Escriba el nombre del paciente.',
            'nombre.max' => 'El nombre no puede pasar de 150 caracteres.',
            'nit.required' => 'Escriba el NIT del paciente o CF.',
            'nit.string' => 'Escriba un NIT válido (con su dígito verificador) o CF.',
            'telefono.required' => 'Escriba el teléfono del paciente.',
            'telefono.string' => 'Escriba un teléfono de Guatemala de 8 dígitos.',
            'correo.string' => 'Escriba un correo válido.',
            'correo.email' => 'Escriba un correo válido.',
            'correo.max' => 'El correo no puede pasar de 150 caracteres.',
        ];
    }

    /**
     * Repetidos comparados ya normalizados, en toda la tabla (el índice único es global),
     * sin contar al paciente que se edita. Solo si el campo no tiene ya un error.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $propio = $this->route('cliente');   // id del paciente que se edita; null al crear

                if (! $validator->errors()->has('nit') && ! Nit::esConsumidorFinal($this->input('nit'))) {
                    $repetido = Cliente::where('nit', Nit::normalizar($this->input('nit')))
                        ->when($propio !== null, fn ($c) => $c->whereKeyNot($propio))
                        ->exists();
                    if ($repetido) {
                        $validator->errors()->add('nit', 'Ya existe un paciente con ese NIT.');
                    }
                }

                if (! $validator->errors()->has('telefono')) {
                    $repetido = Cliente::where('telefono', Telefono::normalizar($this->input('telefono')))
                        ->when($propio !== null, fn ($c) => $c->whereKeyNot($propio))
                        ->exists();
                    if ($repetido) {
                        $validator->errors()->add('telefono', 'Ya existe un paciente con ese teléfono.');
                    }
                }
            },
        ];
    }

    /**
     * Datos listos para guardar: NIT compacto (NULL si es CF), teléfono de 8 dígitos
     * y correo NULL si viene vacío. vendedor_id nunca sale de aquí.
     *
     * @return array{nombre: string, nit: ?string, telefono: string, correo: ?string}
     */
    public function datos(): array
    {
        $v = $this->validated();

        return [
            'nombre' => $v['nombre'],
            'nit' => Nit::esConsumidorFinal($v['nit']) ? null : Nit::normalizar($v['nit']),
            'telefono' => Telefono::normalizar($v['telefono']),
            'correo' => ($v['correo'] ?? '') === '' ? null : $v['correo'],
        ];
    }
}
