@use('App\Models\Oportunidad')
@use('App\Support\Formato')
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planes de tratamiento</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('oportunidades.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="filtro_etapa" value="Etapa" />
                    <select id="filtro_etapa" name="etapa" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todas las etapas</option>
                        @foreach (Oportunidad::ETAPAS_EN_PANTALLA as $valor => $texto)
                            <option value="{{ $valor }}" @selected($etapa === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filtro_asesor" value="Asesor" />
                    <select id="filtro_asesor" name="asesor" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todos los asesores</option>
                        @foreach ($asesores as $a)
                            <option value="{{ $a->id }}" @selected($asesor === (string) $a->id)>
                                {{ $a->name }}{{ $a->activo ? '' : ' (desactivado)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-secondary-button type="submit">Filtrar</x-secondary-button>
                <a href="{{ route('oportunidades.index') }}" class="text-sm text-gray-600 hover:underline">Quitar filtros</a>
            </form>

            <p class="text-sm text-gray-600">{{ $planes->total() }} {{ $planes->total() === 1 ? 'plan' : 'planes' }}</p>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Paciente</th>
                            <th class="px-4 py-3">Título</th>
                            <th class="px-4 py-3">Asesor</th>
                            <th class="px-4 py-3">Etapa</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Fecha de cierre</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($planes as $plan)
                            <tr>
                                <td class="px-4 py-3">{{ $plan->cliente?->nombre }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('oportunidades.show', $plan->id) }}" class="text-indigo-600 hover:underline">{{ $plan->titulo }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $plan->vendedor?->name }}</td>
                                <td class="px-4 py-3">{{ Oportunidad::ETAPAS_EN_PANTALLA[$plan->etapa] ?? $plan->etapa }}</td>
                                <td class="px-4 py-3 text-right">{{ Formato::quetzales($plan->total) }}</td>
                                <td class="px-4 py-3">{{ Formato::fechaGT($plan->fecha_cierre) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay planes de tratamiento con esos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $planes->links() }}
        </div>
    </div>
</x-app-layout>
