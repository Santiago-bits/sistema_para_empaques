<x-auth-shell title="Ingresar">
    <div class="space-y-5">
        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight">Ingresar al sistema</h1>
            <p class="mt-1 text-sm text-stone-500">{{ $google ? 'Ingresá con tus datos de acceso o con tu cuenta de Google.' : 'Ingresá con tus datos de acceso.' }}</p>
        </div>

        @if ($adminArea)
            <div class="rounded-lg border border-brand-300 bg-brand-50 p-3 text-sm text-brand-900 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-200" role="status">
                Para entrar a la <strong>Administración general</strong> primero ingresá con tu usuario de <strong>Super Administrador</strong>
                (el que creaste en el instalador). Después te pide la contraseña una vez más.
            </div>
        @endif

        @if ($errors->has('session'))
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
                {{ $errors->first('session') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf
            <x-input name="login" :label="$label" placeholder="Ej.: jperez o 30123456" hint="Cualquiera de estos datos, tal como figura en tu ficha de usuario."
                     autofocus autocomplete="username" required class="py-2.5 text-base"/>
            <div>
                <x-input name="password" type="password" label="Contraseña" autocomplete="current-password" required class="py-2.5 text-base"/>
                <div class="mt-1.5 text-center">
                    <a href="{{ route('password.request') }}" class="link text-sm">¿Olvidaste tu contraseña?</a>
                </div>
            </div>
            <x-checkbox name="remember" label="Mantener la sesión iniciada en esta computadora" no-hidden/>

            <button type="submit" class="btn btn-primary w-full py-3 text-base">Ingresar</button>
        </form>

        @if ($google)
            @include('auth.partials.google-button')
        @endif
    </div>
</x-auth-shell>
