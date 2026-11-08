<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pacientes</h2>
            <a href="{{ route('clientes.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nuevo paciente
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('clientes.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="q" value="Buscar por nombre o NIT" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block" :value="$q" />
                </div>
                <div>
                    <x-input-label for="filtro_asesor" value="Asesor" />
                    <select id="filtro_asesor" name="asesor" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todos</option>
                        <option value="sin" @selected($asesor === 'sin')>Sin asignar</option>
                        @foreach ($asesores as $a)
                            <option value="{{ $a->id }}" @selected($asesor === (string) $a->id)>
                                {{ $a->name }}{{ $a->activo ? '' : ' (desactivado)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-secondary-button type="submit">Buscar</x-secondary-button>
            </form>

            <p class="text-sm text-gray-600">{{ $clientes->total() }} {{ $clientes->total() === 1 ? 'paciente' : 'pacientes' }}</p>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">NIT</th>
                            <th class="px-4 py-3">Teléfono</th>
                            <th class="px-4 py-3">Correo</th>
                            <th class="px-4 py-3">Asesor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clientes as $cliente)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('clientes.show', $cliente->id) }}" class="text-indigo-600 hover:underline">{{ $cliente->nombre }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $cliente->nitEnPantalla() }}</td>
                                <td class="px-4 py-3">{{ $cliente->telefono }}</td>
                                <td class="px-4 py-3">{{ $cliente->correo }}</td>
                                <td class="px-4 py-3">{{ $cliente->vendedor?->name ?? 'Disponible' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay pacientes que coincidan con la búsqueda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $clientes->links() }}
        </div>
    </div>
</x-app-layout>
