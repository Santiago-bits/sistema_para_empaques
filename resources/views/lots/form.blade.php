<x-layouts.app :title="$lot->exists ? 'Editar lote' : 'Nuevo lote'">
    <x-page-header :title="$lot->exists ? 'Editar lote '.$lot->code : 'Nuevo lote'" :back="$lot->exists ? route('lots.show', $lot) : route('lots.index')"/>

    <form method="POST" action="{{ $lot->exists ? route('lots.update', $lot) : route('lots.store') }}" class="space-y-6">
        @csrf
        @if ($lot->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="code" label="Código" :value="$lot->code" class="code uppercase"
                         :placeholder="$nextCode" :hint="$lot->exists ? null : 'Vacío = numeración automática ('.$nextCode.').'"/>
                <x-input name="date" type="date" label="Fecha" :value="$lot->date" required/>
                <x-select name="season_id" label="Temporada" :options="$seasons" :value="$lot->season_id" placeholder="—"/>
                <x-select name="producer_id" label="Productor" :options="$producers" :value="$lot->producer_id" placeholder="Seleccionar…" required
                          hint="Quién produjo la fruta."/>
                <x-select name="owner_id" label="Propietario" :options="$owners" :value="$lot->owner_id" placeholder="—"
                          hint="A quién pertenece comercialmente."/>
                <x-select name="variety_id" label="Variedad" :options="$varieties" :value="$lot->variety_id" placeholder="—"/>
                <x-input name="origin" label="Origen / procedencia" :value="$lot->origin"/>
                <x-input name="field" label="Campo / cuadro" :value="$lot->field"/>
                <x-input name="bins" type="number" min="0" label="Bines" :value="$lot->bins" hint="Como en la planilla de ingresos."/>
                <x-select name="driver_id" label="Chofer (camionero)" :options="$drivers" :value="$lot->driver_id" placeholder="—"/>
                <x-input name="dtv_number" label="N° de DTV-e" :value="$lot->dtv_number" class="code" placeholder="DTV 1/10"/>
                <x-input name="quantity" type="number" min="0" label="Cantidad de cajones (opcional)" :value="$lot->quantity"/>
                <x-select name="container_type_id" label="Envase" :options="\App\Catalogs\Definitions\ContainerTypeDefinition::options()" :value="$lot->container_type_id" placeholder="—"/>
            </div>
            <h3 class="mt-6 mb-2 text-sm font-semibold text-stone-700 dark:text-stone-300">Compra al productor (opcional)</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="kg_received" inputmode="decimal" label="Kilos recibidos" :value="$lot->kg_received !== null ? num($lot->kg_received, 2) : null" hint="Peso neto de balanza. Ej.: 12.500"/>
                <x-input name="price_per_kg" inputmode="decimal" label="Precio por kilo ($)" :value="$lot->price_per_kg !== null ? num($lot->price_per_kg, 2) : null"
                         hint="Al liquidar el lote, kilos × precio pasan a la cuenta corriente del productor."/>
                @if ($lot->settled_at)
                    <p class="self-end rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">Liquidado el {{ fdate($lot->settled_at) }}. Si corregís kilos, precio o productor, se vuelve a liquidar solo.</p>
                @endif
            </div>
            @if ($lot->settled_at)
                <div class="mt-4"><x-input name="reason" label="Motivo de la corrección (obligatorio si cambiás kilos, precio o productor)" maxlength="255"/></div>
            @endif
            <div class="mt-4">
                <x-textarea name="notes" label="Observaciones" :value="$lot->notes"/>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $lot->exists ? route('lots.show', $lot) : route('lots.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
