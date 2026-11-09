{{-- Formulario compartido por create y edit. $producto es null al crear. --}}
@php
    // Al crear: Activo marcado y Requiere evaluación desmarcado.
    $activoMarcado = (bool) old('activo', $producto ? $producto->activo : true);
    $evaluacionMarcada = (bool) old('requiere_evaluacion', $producto ? $producto->requiere_evaluacion : false);
@endphp

<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" maxlength="120"
                  :value="old('nombre', $producto?->nombre)" required autofocus />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div>
    <x-input-label for="precio" value="Precio" />
    <div class="mt-1 flex items-center gap-2">
        <span class="text-gray-600" aria-hidden="true">Q</span>
        {{-- Tal como está en la base (2750.50), sin Q ni comas. --}}
        <x-text-input id="precio" name="precio" type="text" inputmode="decimal" class="block w-full"
                      :value="old('precio', $producto?->precio)" required />
    </div>
    <x-input-error :messages="$errors->get('precio')" class="mt-2" />
</div>

<div>
    <input type="hidden" name="activo" value="0">
    <label for="activo" class="inline-flex items-center gap-2">
        <input id="activo" name="activo" type="checkbox" value="1" @checked($activoMarcado)
               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <span class="text-sm text-gray-700">Activo</span>
    </label>
    <x-input-error :messages="$errors->get('activo')" class="mt-2" />
</div>

<div>
    <input type="hidden" name="requiere_evaluacion" value="0">
    <label for="requiere_evaluacion" class="inline-flex items-center gap-2">
        <input id="requiere_evaluacion" name="requiere_evaluacion" type="checkbox" value="1" @checked($evaluacionMarcada)
               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <span class="text-sm text-gray-700">Requiere evaluación</span>
    </label>
    <p class="mt-1 text-sm text-gray-600">Para poner un plan con este tratamiento en propuesta o más adelante, el paciente necesita una cita de valoración completada.</p>
    <x-input-error :messages="$errors->get('requiere_evaluacion')" class="mt-2" />
</div>

<div class="flex items-center gap-4">
    <x-primary-button>Guardar</x-primary-button>
    <a href="{{ route('productos.index') }}" class="text-sm text-gray-600 hover:underline">Cancelar</a>
</div>
