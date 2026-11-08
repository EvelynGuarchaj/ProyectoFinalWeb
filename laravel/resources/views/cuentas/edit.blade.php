<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar cuenta: {{ $cuenta->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ route('cuentas.update', $cuenta) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('cuentas.form', ['cuenta' => $cuenta])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
