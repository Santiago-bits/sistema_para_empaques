<x-auth-shell title="Recuperar contraseña">
    <div class="space-y-5">
        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight">Recuperar contraseña</h1>
            <p class="mt-1 text-sm text-stone-500">Te enviamos un código de 6 números a tu email. Después lo ingresás acá y creás tu contraseña nueva.</p>
        </div>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <div class="text-center">
                <x-input name="login" :label="$label" :value="old('login')" placeholder="Ej.: jperez o 30123456" autofocus autocomplete="username" required maxlength="190" class="py-2.5 text-left text-base"/>
            </div>

            <button type="submit" class="btn btn-primary w-full py-3 text-base">Enviarme el código</button>
        </form>

        @if ($google)
            <p class="text-center text-sm text-stone-500">¿Tu email registrado es de Gmail? Podés entrar directo, sin contraseña:</p>
            @include('auth.partials.google-button')
        @endif

        <p class="text-center text-sm"><a href="{{ route('login') }}" class="link">Volver a ingresar</a></p>
        <p class="text-center text-xs text-stone-500"><a href="{{ route('owner.recovery') }}" class="hover:underline">Opción técnica: recuperar el Super Administrador con la contraseña de la base de datos</a></p>
    </div>
</x-auth-shell>
