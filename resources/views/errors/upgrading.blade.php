<x-layouts.guest title="Actualizando">
    <div class="grid min-h-screen place-items-center p-6">
        <div class="panel max-w-md p-8 text-center">
            <x-logo class="mx-auto size-14"/>
            <h1 class="mt-4 text-xl font-semibold">Actualizando el sistema</h1>
            <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">Se está instalando una versión nueva. Tarda unos segundos: esta página se recarga sola.</p>
        </div>
    </div>
    <script>setTimeout(function () { location.reload(); }, 5000);</script>
</x-layouts.guest>
