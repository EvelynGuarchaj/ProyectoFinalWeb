@use('App\Models\Oportunidad')
@use('App\Support\Formato')
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $plan->titulo }}</h2>
            <a href="{{ route('oportunidades.index') }}" class="text-sm text-gray-600 hover:underline">Volver a planes de tratamiento</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-600">Paciente</dt><dd>{{ $plan->cliente?->nombre }}</dd></div>
                    <div><dt class="text-gray-600">Asesor</dt><dd>{{ $plan->vendedor?->name }}</dd></div>
                    <div><dt class="text-gray-600">Etapa</dt><dd>{{ Oportunidad::ETAPAS_EN_PANTALLA[$plan->etapa] ?? $plan->etapa }}</dd></div>
                    <div><dt class="text-gray-600">Fecha de cierre</dt><dd>{{ Formato::fechaGT($plan->fecha_cierre) }}</dd></div>
                    @if ($plan->etapa === 'perdida')
                        <div class="sm:col-span-2"><dt class="text-gray-600">Motivo del rechazo</dt><dd>{{ $plan->motivo_perdida }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <h3 class="px-4 pt-4 font-semibold text-gray-800">Tratamientos</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm mt-2">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Tratamiento</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($lineas as $linea)
                            <tr>
                                <td class="px-4 py-3">{{ $linea->producto?->nombre }}</td>
                                <td class="px-4 py-3 text-right">{{ $linea->cantidad }}</td>
                                <td class="px-4 py-3 text-right">{{ Formato::quetzales($linea->precio_unitario) }}</td>
                                <td class="px-4 py-3 text-right">{{ Formato::quetzales($linea->subtotal) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">Este plan todavía no tiene tratamientos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="3">Total</td>
                            <td class="px-4 py-3 text-right">{{ Formato::quetzales($plan->total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <h3 class="px-4 pt-4 font-semibold text-gray-800">Historial de etapas</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm mt-2">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Etapa anterior</th>
                            <th class="px-4 py-3">Etapa nueva</th>
                            <th class="px-4 py-3">Quién</th>
                            <th class="px-4 py-3">Cuándo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($historial as $registro)
                            <tr>
                                <td class="px-4 py-3">{{ $registro->etapa_anterior === null ? '—' : (Oportunidad::ETAPAS_EN_PANTALLA[$registro->etapa_anterior] ?? $registro->etapa_anterior) }}</td>
                                <td class="px-4 py-3">{{ Oportunidad::ETAPAS_EN_PANTALLA[$registro->etapa_nueva] ?? $registro->etapa_nueva }}</td>
                                <td class="px-4 py-3">{{ $registro->usuario?->name }}</td>
                                <td class="px-4 py-3">{{ Formato::fechaGT($registro->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
