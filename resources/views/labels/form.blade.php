<x-layouts.app title="Etiquetas">
    <x-page-header :title="$type === 'crates' ? 'Etiquetas de cajones' : 'Etiquetas de pallets'"
                   subtitle="Código de barras Code128 + QR. Se imprimen desde el navegador en la impresora de etiquetas configurada en Windows."
                   :back="$type === 'crates' ? route('crates.index') : route('pallets.index')"/>

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($type === 'crates')
            <x-panel title="Reimprimir por rango de códigos">
                <form method="GET" action="{{ route('labels.crates') }}" target="_blank" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="from" label="Desde" class="code" placeholder="CJ-000001" required/>
                        <x-input name="to" label="Hasta" class="code" placeholder="CJ-000050"/>
                    </div>
                    @include('labels._size')
                    <button class="btn btn-primary"><x-icon name="printer" class="size-4"/> Ver e imprimir</button>
                </form>
            </x-panel>

            @can('crates.create')
                <x-panel title="Generar cajones nuevos con etiqueta">
                    <form method="POST" action="{{ route('labels.generate') }}" class="space-y-4" x-data x-confirm="¿Generar los cajones? Se crean con numeración correlativa.">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input name="quantity" type="number" min="1" max="500" label="Cantidad" value="20" required/>
                            <x-select name="pallet_id" label="Pallet" :options="$pallets" placeholder="—"/>
                            <x-select name="lot_id" label="Lote" :options="$lots" placeholder="—"/>
                            <x-select name="variety_id" label="Variedad" :options="$varieties" placeholder="—"/>
                        </div>
                        <button class="btn btn-primary"><x-icon name="plus" class="size-4"/> Generar y abrir etiquetas</button>
                    </form>
                </x-panel>
            @endcan

            <x-panel title="Todas las etiquetas de un pallet">
                <form method="GET" action="{{ route('labels.crates') }}" target="_blank" class="space-y-4">
                    <x-select name="pallet_id" label="Pallet" :options="$pallets" placeholder="Seleccionar…" required/>
                    @include('labels._size')
                    <button class="btn btn-primary"><x-icon name="printer" class="size-4"/> Ver e imprimir</button>
                </form>
            </x-panel>
        @else
            <x-panel title="Seleccionar pallets">
                <form method="GET" action="{{ route('labels.pallets') }}" target="_blank" class="space-y-4">
                    <x-select name="ids[]" label="Pallets" :options="$pallets" multiple size="10" required hint="Ctrl + clic para elegir varios."/>
                    @include('labels._size')
                    <button class="btn btn-primary"><x-icon name="printer" class="size-4"/> Ver e imprimir</button>
                </form>
            </x-panel>
        @endif
    </div>
</x-layouts.app>
