<x-layouts.guest title="Términos y condiciones">
    <div class="flex min-h-screen items-center justify-center bg-stone-50 p-4 dark:bg-stone-950">
        <div class="panel w-full max-w-lg p-6">
            <div class="flex items-center gap-3">
                <x-logo class="size-10"/>
                <h1 class="text-xl font-semibold text-stone-900 dark:text-white">Antes de seguir</h1>
            </div>
            <p class="mt-4 text-sm text-stone-600 dark:text-stone-300">
                Como administrador de tu galpón, necesitamos que leas y aceptes los términos de uso del sistema
                que ofrece {{ $provider['name'] }}. Es una sola vez (y de nuevo sólo si cambian).
            </p>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('legal.terms') }}" target="_blank" class="link font-medium">Términos y condiciones</a> <span class="text-stone-500">(se abre en otra pestaña)</span></li>
                <li><a href="{{ route('legal.privacy') }}" target="_blank" class="link font-medium">Política de privacidad</a></li>
            </ul>

            <form method="POST" action="{{ route('legal.accept.store') }}" class="mt-6 space-y-4">
                @csrf
                <x-checkbox name="accept" label="Leí y acepto los términos y condiciones y la política de privacidad, en nombre de mi galpón." no-hidden/>
                @error('accept')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                <button class="btn btn-primary btn-lg w-full">Aceptar y continuar</button>
            </form>
            <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                @csrf
                <button class="text-sm text-stone-500 hover:underline dark:text-stone-400">Salir</button>
            </form>
            <p class="mt-4 text-xs text-stone-400">Versión {{ \Illuminate\Support\Carbon::parse($version)->format('d/m/Y') }}. Queda registrado quién aceptó, cuándo y desde dónde.</p>
        </div>
    </div>
</x-layouts.guest>
