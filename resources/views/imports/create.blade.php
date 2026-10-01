<x-layouts.app title="Nueva importación">
    <x-page-header title="Nueva importación" :back="route('imports.index')"/>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="1. Subir archivo" class="lg:col-span-2">
            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-select name="type" label="¿Qué vas a importar?" :options="$types" :value="$type" placeholder="Seleccionar…" required/>
                <x-field label="Archivo (CSV o XLSX, máximo 10 MB)" name="file">
                    <input type="file" name="file" accept=".csv,.xlsx,.txt" required class="form-input">
                </x-field>
                <button class="btn btn-primary"><x-icon name="upload" class="size-4"/> Validar archivo</button>
            </form>
        </x-panel>
        <x-panel title="Cómo funciona">
            <ol class="list-inside list-decimal space-y-2 text-sm text-stone-600 dark:text-stone-400">
                <li>Descargá la plantilla del tipo de dato y completala (la primera fila son los encabezados).</li>
                <li>Subí el archivo: el sistema valida <strong>todas</strong> las filas sin guardar nada.</li>
                <li>Revisá el resumen, la vista previa y los errores (podés descargarlos).</li>
                <li>Confirmá: se importan sólo las filas correctas, en una única operación.</li>
            </ol>
        </x-panel>
    </div>
</x-layouts.app>
