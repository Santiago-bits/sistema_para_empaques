<x-auth-shell title="Nueva contraseña">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <div class="text-center">
            <h1 class="text-3xl font-semibold tracking-tight">Crear contraseña nueva</h1>
            <p class="mt-1 text-sm text-stone-500">Código verificado. Al guardarla se cierran tus sesiones abiertas en otras computadoras.</p>
        </div>

        <div class="text-center">
            <x-input name="password" type="password" label="Nueva contraseña" required autofocus autocomplete="new-password" hint="Mínimo 8 caracteres, con letras y números." class="py-2.5 text-left text-base"/>
        </div>
        <div class="text-center">
            <x-input name="password_confirmation" type="password" label="Repetir nueva contraseña" required autocomplete="new-password" class="py-2.5 text-left text-base"/>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3 text-base">Guardar contraseña</button>

        <p class="text-center text-sm"><a href="{{ route('login') }}" class="link">Volver a ingresar</a></p>
    </form>
</x-auth-shell>
