<x-layouts.app title="Etiquetas">
    <x-page-header :title="$type === 'crates' ? 'Etiquetas de cajones' : 'Etiquetas de pallets'"
                   subtitle="Código de barras Code128 + QR. Se imprimen desde el navegador en la impresora de etiquetas configurada en Windows."
                   :back="$type === 'crates' ? route('crates.index') : route('pallets.index')"/>

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($type === 'crates')
            <x-panel title="Reimprimir por rango de códigos">
                {{-- Sólo se escribe el número: «CJ-» va solo. «Hasta» se calcula de a 50 (se puede cambiar a mano). --}}
                <form method="GET" action="{{ route('labels.crates') }}" class="space-y-4"
                      x-data="{ from: @js((int) old('from', $suggestedFrom)), to: @js((int) old('to', $suggestedFrom + \App\Http\Controllers\Crates\LabelController::BLOCK - 1)), edited: false,
                                block: @js(\App\Http\Controllers\Crates\LabelController::BLOCK),
                                code(n) { return @js($cratePrefix) + String(Math.max(0, parseInt(n) || 0)).padStart(@js($cratePadding), '0') } }">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Desde" name="from" :required="true">
                            <div class="flex">
                                <span class="inline-flex items-center border border-r-0 border-stone-300 bg-stone-100 px-3 font-mono text-sm text-stone-600 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300">{{ $cratePrefix }}</span>
                                <input type="number" name="from" id="from" min="1" step="1" required autofocus x-model.number="from"
                                       @input="if (! edited) to = from ? from + block - 1 : ''" class="form-input code" aria-describedby="range-preview">
                            </div>
                        </x-field>
                        <x-field label="Hasta" name="to">
                            <div class="flex">
                                <span class="inline-flex items-center border border-r-0 border-stone-300 bg-stone-100 px-3 font-mono text-sm text-stone-600 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300">{{ $cratePrefix }}</span>
                                <input type="number" name="to" id="to" min="1" step="1" x-model.number="to" @input="edited = true" class="form-input code">
                            </div>
                        </x-field>
                    </div>
                    <p id="range-preview" class="text-sm text-stone-600 dark:text-stone-300" x-show="from" x-cloak>
                        Se imprimen <strong x-text="Math.max(0, (to || from) - from + 1)"></strong> etiquetas:
                        <span class="code" x-text="code(from)"></span> a <span class="code" x-text="code(to || from)"></span>
                        <button type="button" class="link ml-2" x-show="edited" @click="edited = false; to = from + block - 1">Volver a 50</button>
                    </p>
                    @include('labels._size')
                    <button class="btn btn-primary"><x-icon name="printer" class="size-4"/> Ver e imprimir</button>
                </form>
            </x-panel>

            @can('crates.create')
                <x-panel title="Generar cajones nuevos con etiqueta">
                    <form method="POST" action="{{ route('labels.generate') }}" class="space-y-4" x-data x-confirm="¿Generar los cajones? Se crean con numeración correlativa.">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input name="quantity" type="number" min="1" max="500" label="Cantidad" value="50" required hint="De a 50 para mantener el orden."/>
                            <x-select name="pallet_id" label="Pallet" :options="$pallets" placeholder="—"/>
                            <x-select name="lot_id" label="Lote" :options="$lots" placeholder="—"/>
                            <x-select name="variety_id" label="Variedad" :options="$varieties" placeholder="—"/>
                            <x-select name="size_id" label="Calibre / tamaño" :options="\App\Models\Size::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()" placeholder="—"/>
                            <x-select name="grade_id" label="Selección" :options="\App\Catalogs\Definitions\GradeDefinition::options()" placeholder="—"/>
                            <x-select name="container_type_id" label="Envase" :options="\App\Catalogs\Definitions\ContainerTypeDefinition::options()" :value="\App\Models\ContainerType::defaultForCrates()" placeholder="—"/>
                        </div>
                        <button class="btn btn-primary"><x-icon name="plus" class="size-4"/> Generar y abrir etiquetas</button>
                    </form>
                </x-panel>
            @endcan

            <x-panel title="Todas las etiquetas de un pallet">
                <form method="GET" action="{{ route('labels.crates') }}" class="space-y-4">
                    <x-select name="pallet_id" label="Pallet" :options="$pallets" placeholder="Seleccionar…" required/>
                    @include('labels._size')
                    <button class="btn btn-primary"><x-icon name="printer" class="size-4"/> Ver e imprimir</button>
                </form>
            </x-panel>
        @else
            <x-panel title="Seleccionar pallets">
                <form method="GET" action="{{ route('labels.pallets') }}" class="space-y-4">
                    <x-select name="ids[]" label="Pallets" :options="$pallets" multiple size="10" required hint="Ctrl + clic para elegir varios."/>
                    @include('labels._size')
                    <button class="btn btn-primary"><x-icon name="printer" class="size-4"/> Ver e imprimir</button>
                </form>
            </x-panel>
        @endif
    </div>
</x-layouts.app>
