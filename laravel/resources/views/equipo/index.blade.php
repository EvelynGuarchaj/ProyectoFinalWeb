@php
    use App\Services\Cartera;
    use App\Support\Formato;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mi equipo</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('equipo.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="mes" value="Mes" />
                    <x-text-input id="mes" name="mes" type="month" class="mt-1 block" :value="$mes" />
                </div>
                <x-secondary-button type="submit">Ver</x-secondary-button>
            </form>

            @if ($avisoMes)
                <div class="p-4 bg-yellow-50 text-yellow-800 rounded-md">{{ $avisoMes }}</div>
            @endif

            <h3 class="font-semibold text-lg text-gray-800">Ventas de {{ Formato::mesEnPalabras($mes) }}</h3>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Asesor</th>
                            <th class="px-4 py-3">Cartera</th>
                            <th class="px-4 py-3 text-right">Ventas del mes</th>
                            <th class="px-4 py-3 text-right">Total vendido</th>
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
                                <td class="px-4 py-3">{{ $asesor->cartera }} de {{ Cartera::MAXIMO }}</td>
                                <td class="px-4 py-3 text-right">{{ $asesor->ventas }}</td>
                                <td class="px-4 py-3 text-right">{{ Formato::quetzales($asesor->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">Su equipo todavía no tiene asesores.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
