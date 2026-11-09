@php
    use App\Services\Cartera;
    use App\Support\Formato;

    $filaTotal = $esAdmin ? 'Toda la clínica' : 'Total del equipo';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reporte del mes</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('reportes.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="mes" value="Mes" />
                    <x-text-input id="mes" name="mes" type="month" class="mt-1 block" :value="$mes" />
                </div>
                <x-secondary-button type="submit">Ver</x-secondary-button>
            </form>

            @if ($avisoMes)
                <div class="p-4 bg-yellow-50 text-yellow-800 rounded-md">{{ $avisoMes }}</div>
            @endif

            <h3 class="font-semibold text-lg text-gray-800">Reporte de {{ Formato::mesEnPalabras($mes) }}</h3>

            {{-- Por asesor --}}
            <section class="space-y-2">
                <h4 class="font-semibold text-gray-700">Por asesor</h4>
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Asesor</th>
                                @if ($esAdmin)
                                    <th class="px-4 py-3">Coordinador</th>
                                @endif
                                <th class="px-4 py-3">Cartera</th>
                                <th class="px-4 py-3 text-right">Ventas del mes</th>
                                <th class="px-4 py-3 text-right">Total vendido</th>
                                <th class="px-4 py-3 text-right">Pronóstico</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($asesores as $asesor)
                                <tr>
                                    <td class="px-4 py-3">
                                        {{ $asesor->name }}
                                        @unless ($asesor->activo)
                                            <span class="ms-2 px-2 py-0.5 rounded bg-gray-200 text-gray-700 text-xs">Desactivado</span>
                                        @endunless
                                    </td>
                                    @if ($esAdmin)
                                        <td class="px-4 py-3">{{ $asesor->coordinador }}</td>
                                    @endif
                                    <td class="px-4 py-3">{{ $asesor->cartera }} de {{ Cartera::MAXIMO }}</td>
                                    <td class="px-4 py-3 text-right">{{ $asesor->ventas }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($asesor->total) }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($asesor->pronostico) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $esAdmin ? 6 : 5 }}" class="px-4 py-6 text-center text-gray-500">Su equipo todavía no tiene asesores.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold text-gray-800">
                            <tr>
                                <td class="px-4 py-3" colspan="{{ $esAdmin ? 3 : 2 }}">{{ $filaTotal }}</td>
                                <td class="px-4 py-3 text-right">{{ $totales->ventas }}</td>
                                <td class="px-4 py-3 text-right">{{ Formato::quetzales($totales->total) }}</td>
                                <td class="px-4 py-3 text-right">{{ Formato::quetzales($totales->pronostico) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            {{-- Por equipo (solo administrador) --}}
            @if ($esAdmin)
                <section class="space-y-2">
                    <h4 class="font-semibold text-gray-700">Por equipo</h4>
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-gray-600">
                                <tr>
                                    <th class="px-4 py-3">Coordinador</th>
                                    <th class="px-4 py-3 text-right">Ventas del mes</th>
                                    <th class="px-4 py-3 text-right">Total vendido</th>
                                    <th class="px-4 py-3 text-right">Pronóstico</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($equipos as $equipo)
                                    <tr>
                                        <td class="px-4 py-3">
                                            Equipo de {{ $equipo->name }}
                                            @unless ($equipo->activo)
                                                <span class="ms-2 px-2 py-0.5 rounded bg-gray-200 text-gray-700 text-xs">Desactivado</span>
                                            @endunless
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ $equipo->ventas }}</td>
                                        <td class="px-4 py-3 text-right">{{ Formato::quetzales($equipo->total) }}</td>
                                        <td class="px-4 py-3 text-right">{{ Formato::quetzales($equipo->pronostico) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold text-gray-800">
                                <tr>
                                    <td class="px-4 py-3">Toda la clínica</td>
                                    <td class="px-4 py-3 text-right">{{ $totales->ventas }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($totales->total) }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($totales->pronostico) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @endif

            {{-- Por tratamiento --}}
            <section class="space-y-2">
                <h4 class="font-semibold text-gray-700">Por tratamiento</h4>
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Tratamiento</th>
                                <th class="px-4 py-3 text-right">Unidades vendidas</th>
                                <th class="px-4 py-3 text-right">Total vendido</th>
                                <th class="px-4 py-3 text-right">Pronóstico</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($tratamientos as $tratamiento)
                                <tr>
                                    <td class="px-4 py-3">{{ $tratamiento->nombre }}</td>
                                    <td class="px-4 py-3 text-right">{{ $tratamiento->unidades }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($tratamiento->total) }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($tratamiento->pronostico) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No hay ventas ni planes abiertos en este mes.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($tratamientos->isNotEmpty())
                            <tfoot class="bg-gray-50 font-semibold text-gray-800">
                                <tr>
                                    <td class="px-4 py-3">Total</td>
                                    <td class="px-4 py-3 text-right">{{ $totalTratamientos->unidades }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($totalTratamientos->total) }}</td>
                                    <td class="px-4 py-3 text-right">{{ Formato::quetzales($totalTratamientos->pronostico) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
