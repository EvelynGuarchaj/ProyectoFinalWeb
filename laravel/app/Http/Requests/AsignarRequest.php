<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\User;
use App\Services\Cartera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Asignar o reasignar un paciente (L4 parte 2, spec 07 sección 2.3).
 * La ruta ya pasa por rol:administrador,supervisor.
 */
class AsignarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lo ajeno responde 404 antes de validar (S5), igual que ClienteRequest.
     * Cada paciente usa su propia bolsa de errores, así en la lista el error
     * sale solo debajo del selector de ese paciente.
     */
    protected function prepareForValidation(): void
    {
        $cliente = Cliente::visiblePara($this->user())->findOrFail($this->route('cliente'));
        $this->errorBag = 'asignar'.$cliente->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vendedor_id' => ['bail', 'required', 'integer', self::asesorAsignable($this->user())],
        ];
    }

    /**
     * Asesor activo y, para el coordinador, de su equipo (sección 3.10).
     * Lo comparten AsignarRequest y el alta de ClienteRequest.
     */
    public static function asesorAsignable(User $usuario): Exists
    {
        return Rule::exists('users', 'id')
            ->where('rol', 'vendedor')
            ->where('activo', 1)
            ->when($usuario->esSupervisor(), fn (Exists $r) => $r->where('supervisor_id', $usuario->id));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vendedor_id.required' => Cartera::ASESOR_NO_VALIDO,
            'vendedor_id.integer' => Cartera::ASESOR_NO_VALIDO,
            'vendedor_id.exists' => Cartera::ASESOR_NO_VALIDO,
        ];
    }
}
