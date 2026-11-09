<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuentas</h2>
            <a href="{{ route('cuentas.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nueva cuenta
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('cuentas.index') }}" class="flex items-end gap-3">
                <div>
                    <x-input-label for="filtro_rol" value="Rol" />
                    <select id="filtro_rol" name="rol" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todos</option>
                        @foreach (\App\Models\User::ROLES as $valor => $nombre)
                            <option value="{{ $valor }}" @selected($rol === $valor)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <x-secondary-button type="submit">Filtrar</x-secondary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Correo</th>
                            <th class="px-4 py-3">Rol</th>
                            <th class="px-4 py-3">Coordinador</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cuentas as $cuenta)
                            <tr>
                                <td class="px-4 py-3">{{ $cuenta->name }}</td>
                                <td class="px-4 py-3">{{ $cuenta->email }}</td>
                                <td class="px-4 py-3">{{ $cuenta->rolEnPantalla() }}</td>
                                <td class="px-4 py-3">{{ $cuenta->supervisor?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $cuenta->activo ? 'Activa' : 'Desactivada' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('cuentas.edit', $cuenta) }}" class="text-indigo-600 hover:underline">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay cuentas con ese rol.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
