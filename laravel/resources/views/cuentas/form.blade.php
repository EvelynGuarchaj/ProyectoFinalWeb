{{-- Formulario compartido por create y edit. $cuenta es null al crear. --}}
@php
    $rolElegido = old('rol', $cuenta?->rol ?? '');
    $activoElegido = (string) old('activo', $cuenta ? (int) $cuenta->activo : 1);
@endphp

<div>
    <x-input-label for="name" value="Nombre" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" maxlength="255"
                  :value="old('name', $cuenta?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="email" value="Correo" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" maxlength="255"
                  :value="old('email', $cuenta?->email)" required />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>

<div>
    <x-input-label for="password" value="Contraseña" />
    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
    @if ($cuenta)
        <p class="mt-1 text-sm text-gray-600">Déjela vacía para conservar la actual.</p>
    @endif
    <x-input-error :messages="$errors->get('password')" class="mt-2" />
</div>

<div>
    <x-input-label for="password_confirmation" value="Confirme la contraseña" />
    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
</div>

<div>
    <x-input-label for="rol" value="Rol" />
    <select id="rol" name="rol" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        <option value="">Elija el rol</option>
        @foreach (\App\Models\User::ROLES as $valor => $nombre)
            <option value="{{ $valor }}" @selected($rolElegido === $valor)>{{ $nombre }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('rol')" class="mt-2" />
</div>

<div id="campo_supervisor">
    <x-input-label for="supervisor_id" value="Coordinador" />
    <select id="supervisor_id" name="supervisor_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        <option value="">Elija el coordinador</option>
        @foreach ($supervisores as $supervisor)
            <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id', $cuenta?->supervisor_id) === (string) $supervisor->id)>{{ $supervisor->name }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-sm text-gray-600">Solo para cuentas de asesor.</p>
    <x-input-error :messages="$errors->get('supervisor_id')" class="mt-2" />
</div>

<div>
    <x-input-label for="activo" value="Estado" />
    <select id="activo" name="activo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        <option value="1" @selected($activoElegido === '1')>Activa</option>
        <option value="0" @selected($activoElegido === '0')>Desactivada</option>
    </select>
    <x-input-error :messages="$errors->get('activo')" class="mt-2" />
</div>

<div class="flex items-center gap-4">
    <x-primary-button>Guardar</x-primary-button>
    <a href="{{ route('cuentas.index') }}" class="text-sm text-gray-600 hover:underline">Cancelar</a>
</div>

<script>
    // El coordinador solo se muestra para Asesor. Sin JavaScript se ve siempre
    // y el servidor descarta el valor para los demás roles.
    (function () {
        const rol = document.getElementById('rol');
        const campo = document.getElementById('campo_supervisor');
        const actualizar = () => { campo.hidden = rol.value !== 'vendedor'; };
        rol.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
