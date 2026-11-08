@use('App\Models\Oportunidad')
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $cliente->nombre }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('clientes.edit', $cliente->id) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Editar
                </a>
                @if (auth()->user()->esAdmin())
                    <form method="POST" action="{{ route('clientes.destroy', $cliente->id) }}"
                          onsubmit="return confirm('¿Eliminar este paciente? Se borrarán también sus citas, llamadas y tareas.')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Eliminar</x-danger-button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="p-4 bg-red-50 text-red-800 rounded-md">{{ session('error') }}</div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-600">Nombre</dt><dd>{{ $cliente->nombre }}</dd></div>
                    <div><dt class="text-gray-600">NIT</dt><dd>{{ $cliente->nitEnPantalla() }}</dd></div>
                    <div><dt class="text-gray-600">Teléfono</dt><dd>{{ $cliente->telefono }}</dd></div>
                    <div><dt class="text-gray-600">Correo</dt><dd>{{ $cliente->correo ?? '—' }}</dd></div>
                    <div><dt class="text-gray-600">Asesor</dt><dd>{{ $cliente->vendedor?->name ?? 'Disponible' }}</dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap items-start gap-3">
                    @include('clientes._asignar')
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <h3 class="px-4 pt-4 font-semibold text-gray-800">Planes de tratamiento</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm mt-2">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Título</th>
                            <th class="px-4 py-3">Etapa</th>
                            <th class="px-4 py-3">Asesor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($planes as $plan)
                            <tr>
                                <td class="px-4 py-3">
                                    @if (in_array($plan->id, $planesVisibles, true))
                                        <a href="{{ route('oportunidades.show', $plan->id) }}" class="text-indigo-600 hover:underline">{{ $plan->titulo }}</a>
                                    @else
                                        {{ $plan->titulo }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ Oportunidad::ETAPAS_EN_PANTALLA[$plan->etapa] ?? $plan->etapa }}</td>
                                <td class="px-4 py-3">{{ $plan->vendedor?->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">Este paciente no tiene planes de tratamiento.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <a href="{{ route('clientes.index') }}" class="text-sm text-gray-600 hover:underline">Volver a Pacientes</a>
        </div>
    </div>
</x-app-layout>
