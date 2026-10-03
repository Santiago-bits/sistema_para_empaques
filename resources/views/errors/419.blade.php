<x-layouts.guest title="Sesión expirada">
    <div class="grid min-h-screen place-items-center p-6">
        <div class="panel max-w-md p-8 text-center">
            <p class="code text-5xl font-bold text-stone-300 dark:text-stone-700">419</p>
            <h1 class="mt-3 text-xl font-semibold">Sesión expirada</h1>
            @isset($hint)
                <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">No se pudo mantener la sesión del navegador. {{ $hint }}</p>
            @else
                <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">La página expiró por inactividad. Volvé a intentarlo.</p>
            @endisset
            <a href="{{ $retry ?? url('/') }}" class="btn btn-primary mt-6">{{ isset($retry) ? 'Volver a intentar' : 'Volver al inicio' }}</a>
        </div>
    </div>
</x-layouts.guest>
