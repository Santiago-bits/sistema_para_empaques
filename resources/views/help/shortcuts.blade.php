<x-layouts.app title="Ayuda y atajos">
    <x-page-header title="Ayuda y atajos de teclado"
                   subtitle="Trabajá sin mouse, como en Excel. Apretá ? en cualquier pantalla para ver esta lista."/>

    <x-panel title="Instalar el sistema como app (ícono en el escritorio o en el celular)" class="mb-4">
        <div class="grid gap-4 text-sm text-stone-600 md:grid-cols-3 dark:text-stone-300">
            <div>
                <p class="font-semibold text-stone-900 dark:text-white">En la computadora (Chrome o Edge)</p>
                <p class="mt-1">Tocá el botón verde <strong>«Instalar app»</strong> de arriba (o el ícono de instalar que aparece a la derecha de la barra de direcciones) y aceptá.
                    Queda un ícono en el escritorio y en el menú Inicio, y se abre en su propia ventana como cualquier programa.</p>
            </div>
            <div>
                <p class="font-semibold text-stone-900 dark:text-white">En un celular Android</p>
                <p class="mt-1">Abrí el sistema en Chrome, tocá los tres puntitos <strong>⋮</strong> y elegí <strong>«Instalar app»</strong> (o «Agregar a pantalla principal»).</p>
            </div>
            <div>
                <p class="font-semibold text-stone-900 dark:text-white">En un iPhone</p>
                <p class="mt-1">Abrí el sistema en Safari, tocá el botón <strong>Compartir</strong> (el cuadrado con la flecha) y elegí <strong>«Agregar a inicio»</strong>.</p>
            </div>
        </div>
        <p class="mt-3 text-xs text-stone-500">La app se actualiza sola: cuando subís una versión nueva al servidor, todos la ven la próxima vez que la abren.</p>
    </x-panel>

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
