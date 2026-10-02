<x-auth-shell title="Recuperar contraseña">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Recuperar contraseña</h1>
            <p class="mt-1 text-sm text-stone-500">Ingresá tu {{ $hint }}. Si tenés email registrado te enviamos un enlace para crear una contraseña nueva; si no, le avisamos al administrador para que te asigne una temporal.</p>
        </div>

        <x-input name="login" label="Usuario" :value="old('login')" autofocus autocomplete="username" required maxlength="190" class="py-2.5 text-base"/>

        <button type="submit" class="btn btn-primary w-full py-3 text-base">Recuperar acceso</button>

        <p class="text-center text-sm"><a href="{{ route('login') }}" class="link">Volver a ingresar</a></p>
    </form>
</x-auth-shell>
