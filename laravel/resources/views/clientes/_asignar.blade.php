{{-- Asignar, reasignar y liberar un paciente (spec 07). Recibe $cliente y $asignables. --}}
@php($error = $errors->getBag('asignar'.$cliente->id)->first('vendedor_id'))
<div>
    <form method="POST" action="{{ route('clientes.asignar', $cliente->id) }}" class="flex items-center gap-2">
        @csrf
        @method('PATCH')
        <label for="vendedor_id_{{ $cliente->id }}" class="sr-only">Asesor de {{ $cliente->nombre }}</label>
        <select id="vendedor_id_{{ $cliente->id }}" name="vendedor_id"
                class="border-gray-300 rounded-md shadow-sm text-sm py-1">
            @unless ($asignables->contains('id', $cliente->vendedor_id))
                <option value="">Elija un asesor</option>
            @endunless
            @foreach ($asignables as $a)
                <option value="{{ $a->id }}" @selected($a->id === $cliente->vendedor_id)>
                    {{ $a->name }} ({{ $a->clientes_count }} de {{ \App\Services\Cartera::MAXIMO }})
                </option>
            @endforeach
        </select>
        <x-secondary-button type="submit">Asignar</x-secondary-button>
    </form>
    @if ($cliente->vendedor_id !== null)
        <form method="POST" action="{{ route('clientes.liberar', $cliente->id) }}" class="mt-1">
            @csrf
            @method('PATCH')
            <x-secondary-button type="submit">Liberar</x-secondary-button>
        </form>
    @endif
    @if ($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
