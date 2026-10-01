<x-layouts.guest title="Demasiados intentos">
    <div class="grid min-h-screen place-items-center p-6">
        <div class="panel max-w-md p-8 text-center">
            <p class="code text-5xl font-bold text-stone-300 dark:text-stone-700">429</p>
            <h1 class="mt-3 text-xl font-semibold">Demasiados intentos</h1>
            <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">Esperá un momento antes de volver a intentar.</p>
            <a href="{{ url('/') }}" class="btn btn-primary mt-6">Volver al inicio</a>
        </div>
    </div>
</x-layouts.guest>
