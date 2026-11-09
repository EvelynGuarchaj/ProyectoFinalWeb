<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de tratamientos (L3, spec 04 sección 2.4).
 * La ruta ya pasa por rol:administrador.
 */
class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Producto|null $producto  null al crear */
        $producto = $this->route('producto');

        return [
            'nombre' => [
                'bail', 'required', 'string', 'max:120',
                Rule::unique('productos', 'nombre')->ignore($producto),
            ],
            'precio' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            // Las casillas llegan siempre: un input oculto envía 0 si están desmarcadas.
            'activo' => ['required', 'boolean'],
            'requiere_evaluacion' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre del tratamiento.',
            'nombre.string' => 'Escriba el nombre del tratamiento.',
            'nombre.max' => 'El nombre no puede pasar de 120 caracteres.',
            'nombre.unique' => 'Ya existe un tratamiento con ese nombre.',
            'precio.required' => 'Escriba el precio.',
            'precio.numeric' => 'Escriba el precio como un número, por ejemplo 150.00.',
            'precio.gt' => 'El precio debe ser mayor que cero.',
            'precio.decimal' => 'El precio puede tener como máximo dos decimales.',
            'precio.max' => 'El precio no puede pasar de Q99,999,999.99.',
            'activo.required' => 'Indique si el tratamiento está activo.',
            'activo.boolean' => 'Indique si el tratamiento está activo.',
            'requiere_evaluacion.required' => 'Indique si el tratamiento requiere evaluación.',
            'requiere_evaluacion.boolean' => 'Indique si el tratamiento requiere evaluación.',
        ];
    }
}
