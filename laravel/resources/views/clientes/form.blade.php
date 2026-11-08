{{-- Formulario compartido por create y edit. $cliente es null al crear. --}}
<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" maxlength="150"
                  :value="old('nombre', $cliente?->nombre)" required autofocus />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div>
    <x-input-label for="nit" value="NIT" />
    <x-text-input id="nit" name="nit" type="text" class="mt-1 block w-full"
                  :value="old('nit', $cliente?->nitEnPantalla())" required />
    <p class="mt-1 text-sm text-gray-600">Con su dígito verificador, por ejemplo 4521817-K, o CF.</p>
    <x-input-error :messages="$errors->get('nit')" class="mt-2" />
</div>

<div>
    <x-input-label for="telefono" value="Teléfono" />
    <x-text-input id="telefono" name="telefono" type="tel" class="mt-1 block w-full"
                  :value="old('telefono', $cliente?->telefono)" required />
    <p class="mt-1 text-sm text-gray-600">8 dígitos; puede incluir +502.</p>
    <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
</div>

<div>
    <x-input-label for="correo" value="Correo (opcional)" />
    {{-- type="text" para que el servidor, no el navegador, dé el mensaje en español. --}}
    <x-text-input id="correo" name="correo" type="text" inputmode="email" class="mt-1 block w-full" maxlength="150"
                  :value="old('correo', $cliente?->correo)" />
    <x-input-error :messages="$errors->get('correo')" class="mt-2" />
</div>

@if ($cliente === null)
    {{-- Asesor opcional solo al crear (spec 07). Editar no cambia el asesor. --}}
    <div>
        <x-input-label for="vendedor_id" value="Asesor" />
        <select id="vendedor_id" name="vendedor_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option value="">Disponible (sin asesor)</option>
            @foreach ($asignables as $a)
                <option value="{{ $a->id }}" @selected((string) old('vendedor_id') === (string) $a->id)>
                    {{ $a->name }} ({{ $a->clientes_count }} de {{ \App\Services\Cartera::MAXIMO }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('vendedor_id')" class="mt-2" />
    </div>
@endif

<div class="flex items-center gap-4">
    <x-primary-button>Guardar</x-primary-button>
    <a href="{{ route('clientes.index') }}" class="text-sm text-gray-600 hover:underline">Cancelar</a>
</div>
