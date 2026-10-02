<x-auth-shell title="Cambiar contraseña">
    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Elegí una contraseña nueva</h1>
            <p class="mt-1 text-sm text-stone-500">Hola {{ auth()->user()->first_name }}. Estás usando una contraseña temporal; para seguir tenés que reemplazarla por una propia.</p>
        </div>

        <x-input name="current_password" type="password" label="Contraseña temporal" required autofocus autocomplete="current-password" class="py-2.5 text-base"/>
        <x-input name="password" type="password" label="Nueva contraseña" required autocomplete="new-password" hint="Mínimo 8 caracteres, con letras y números." class="py-2.5 text-base"/>
        <x-input name="password_confirmation" type="password" label="Repetir nueva contraseña" required autocomplete="new-password" class="py-2.5 text-base"/>

        <button type="submit" class="btn btn-primary w-full py-3 text-base">Guardar y continuar</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button class="link text-sm">Salir</button>
    </form>
</x-auth-shell>
