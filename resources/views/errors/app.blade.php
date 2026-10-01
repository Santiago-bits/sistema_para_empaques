<x-layouts.guest title="Error">
    <div class="grid min-h-screen place-items-center p-6">
        <div class="panel max-w-md p-8 text-center">
            <x-icon name="alert" class="mx-auto size-12 text-accent-500"/>
            <h1 class="mt-4 text-xl font-semibold">No se pudo completar la operación</h1>
            <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">Ocurrió un error inesperado. Ningún dato quedó a medio guardar.</p>
            <p class="mt-4 text-sm">Código de referencia:</p>
            <p class="code mt-1 inline-block rounded-md bg-stone-100 px-3 py-1.5 text-base dark:bg-stone-800">{{ $code }}</p>
            <p class="mt-4 text-xs text-stone-500">Si el problema continúa, informá este código a soporte.</p>
            <a href="{{ url('/') }}" class="btn btn-primary mt-6">Volver al inicio</a>
        </div>
    </div>
</x-layouts.guest>
