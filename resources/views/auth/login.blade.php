<x-auth-shell title="Ingresar">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Ingresar al sistema</h1>
            <p class="mt-1 text-sm text-stone-500">Usá tu {{ $hint }}.</p>
        </div>

        <x-input name="login" label="Usuario" autofocus autocomplete="username" required class="py-2.5 text-base"/>
        <div>
            <x-input name="password" type="password" label="Contraseña" autocomplete="current-password" required class="py-2.5 text-base"/>
            <div class="mt-1.5 text-right">
                <a href="{{ route('password.request') }}" class="link text-sm">¿Olvidaste tu contraseña?</a>
            </div>
        </div>
        <x-checkbox name="remember" label="Mantener la sesión iniciada en esta computadora" no-hidden/>

        <button type="submit" class="btn btn-primary w-full py-3 text-base">Ingresar</button>
    </form>
</x-auth-shell>
