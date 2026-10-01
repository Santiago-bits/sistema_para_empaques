<x-layouts.guest title="Acceso denegado">
    <div class="grid min-h-screen place-items-center p-6">
        <div class="panel max-w-md p-8 text-center">
            <p class="code text-5xl font-bold text-stone-300 dark:text-stone-700">403</p>
            <h1 class="mt-3 text-xl font-semibold">Acceso denegado</h1>
            <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">{{ (isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.') ? $exception->getMessage() : 'No tenés permiso para acceder a esta sección.' }}</p>
            <a href="{{ url('/') }}" class="btn btn-primary mt-6">Volver al inicio</a>
        </div>
    </div>
</x-layouts.guest>
