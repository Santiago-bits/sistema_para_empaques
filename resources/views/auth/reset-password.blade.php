<x-auth-shell title="Nueva contraseña">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Crear contraseña nueva</h1>
            <p class="mt-1 text-sm text-stone-500">Al guardarla se cierran todas tus sesiones abiertas en otras computadoras.</p>
        </div>

        <x-input name="email" type="email" label="Email" :value="old('email', $email)" required autocomplete="username" class="py-2.5 text-base"/>
        <x-input name="password" type="password" label="Nueva contraseña" required autofocus autocomplete="new-password" hint="Mínimo 8 caracteres, con letras y números." class="py-2.5 text-base"/>
        <x-input name="password_confirmation" type="password" label="Repetir nueva contraseña" required autocomplete="new-password" class="py-2.5 text-base"/>

        <button type="submit" class="btn btn-primary w-full py-3 text-base">Guardar contraseña</button>

        <p class="text-center text-sm"><a href="{{ route('login') }}" class="link">Volver a ingresar</a></p>
    </form>
</x-auth-shell>
