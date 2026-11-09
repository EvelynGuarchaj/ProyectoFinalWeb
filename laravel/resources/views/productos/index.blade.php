@use('App\Support\Formato')

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tratamientos</h2>
            <a href="{{ route('productos.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nuevo tratamiento
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3">Requiere evaluación</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($productos as $producto)
                            <tr>
                                <td class="px-4 py-3">{{ $producto->nombre }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ Formato::quetzales($producto->precio) }}</td>
                                <td class="px-4 py-3">{{ $producto->requiere_evaluacion ? 'Sí' : 'No' }}</td>
                                <td class="px-4 py-3">{{ $producto->activo ? 'Activo' : 'Desactivado' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('productos.edit', $producto) }}" class="text-indigo-600 hover:underline">Editar</a>
                                    <form method="POST" action="{{ route('productos.estado', $producto) }}" class="inline ms-3">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-gray-600 hover:underline">
                                            {{ $producto->activo ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">Todavía no hay tratamientos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
