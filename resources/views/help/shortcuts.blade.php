<x-layouts.app title="Ayuda y atajos">
    <x-page-header title="Ayuda y atajos de teclado"
                   subtitle="Trabajá sin mouse, como en Excel. Apretá ? en cualquier pantalla para ver esta lista."/>

    <div class="grid gap-4 md:grid-cols-3">
        <x-panel title="Cómo se usan">
            <ul class="space-y-2 text-sm text-stone-600 dark:text-stone-300">
                <li><kbd class="kbd">Ctrl</kbd> <span class="text-stone-400">+</span> <kbd class="kbd">S</kbd>: apretá las dos teclas juntas.</li>
                <li><kbd class="kbd">G</kbd> <span class="text-stone-400">luego</span> <kbd class="kbd">C</kbd>: primero G, la soltás y enseguida C.</li>
                <li>Las teclas de una sola letra no se activan mientras escribís en un campo. Apretá <kbd class="kbd">Esc</kbd> para salir del campo.</li>
            </ul>
        </x-panel>
        <x-panel title="Lector de códigos y cámara">
            <ul class="space-y-2 text-sm text-stone-600 dark:text-stone-300">
                <li>El lector (tipo supermercado) escribe el código y aprieta <kbd class="kbd">Enter</kbd> solo: la pantalla avanza sin tocar nada.</li>
                <li>En celular o tablet tocá <strong>Cámara</strong> al lado del campo para leer la etiqueta.</li>
            </ul>
        </x-panel>
        <x-panel title="Sólo lo que podés usar">
            <p class="text-sm text-stone-600 dark:text-stone-300">
                La lista muestra los atajos de las secciones habilitadas para tu usuario. Si necesitás otra sección,
                pedila al administrador.
            </p>
        </x-panel>
    </div>

    <x-panel class="mt-4">
        @include('help._shortcuts', ['groups' => $groups])
    </x-panel>
</x-layouts.app>
