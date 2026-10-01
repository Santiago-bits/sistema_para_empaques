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
                <x-input name="quantity" type="number" min="0" label="Cantidad (bins/cajones)" :value="$lot->quantity"/>
            </div>
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
