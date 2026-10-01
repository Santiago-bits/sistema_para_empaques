<x-layouts.app :title="$pallet->exists ? 'Editar pallet' : 'Ingresar pallet'">
    <x-page-header :title="$pallet->exists ? 'Editar pallet '.$pallet->code : 'Ingreso de pallet'"
                   :back="$pallet->exists ? route('pallets.show', $pallet) : route('pallets.index')"/>

    <form method="POST" action="{{ $pallet->exists ? route('pallets.update', $pallet) : route('pallets.store') }}" class="space-y-6">
        @csrf
        @if ($pallet->exists) @method('PUT') @endif

        <x-panel title="Identificación">
            <div class="grid gap-4 md:grid-cols-3">
                @if ($pallet->exists)
                    <x-field label="Código"><p class="code py-2 text-lg">{{ $pallet->code }}</p></x-field>
                @else
                    <x-input name="code" label="Código" class="code" :placeholder="$nextCode" autofocus
                             hint="Escaneá la etiqueta o dejalo vacío para numerar automáticamente."/>
                @endif
                <x-input name="barcode" label="Código de barras (opcional)" :value="$pallet->barcode" class="code"/>
                <x-input name="received_at" type="datetime-local" label="Fecha y hora de ingreso" :value="$pallet->received_at" required/>
            </div>
        </x-panel>

        <x-panel title="Origen y propiedad">
            <div class="grid gap-4 md:grid-cols-3">
                <x-select name="lot_id" label="Lote" :options="$lots" :value="$pallet->lot_id" placeholder="Sin lote"
                          hint="Si elegís un lote, productor/propietario/variedad se completan desde él."/>
                <x-select name="producer_id" label="Productor" :options="$producers" :value="$pallet->producer_id" placeholder="—" hint="Quién produjo la fruta."/>
                <x-select name="owner_id" label="Propietario" :options="$owners" :value="$pallet->owner_id" placeholder="—" hint="A quién pertenece comercialmente."/>
                <x-select name="variety_id" label="Variedad" :options="$varieties" :value="$pallet->variety_id" placeholder="—"/>
                <x-input name="origin" label="Procedencia" :value="$pallet->origin"/>
                <x-select name="location_id" label="Ubicación" :options="$locations" :value="$pallet->location_id" placeholder="Sin ubicar"/>
            </div>
        </x-panel>

        <x-panel title="Contenido">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="quantity" type="number" min="0" label="Cantidad de cajones/bins" :value="$pallet->quantity"/>
                <x-input name="gross_weight" type="number" step="0.01" min="0" label="Peso bruto (kg)" :value="$pallet->gross_weight"/>
            </div>
            <div class="mt-4">
                <x-textarea name="notes" label="Observaciones" :value="$pallet->notes"/>
            </div>
        </x-panel>

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ $pallet->exists ? route('pallets.show', $pallet) : route('pallets.index') }}" class="btn btn-secondary">Cancelar</a>
            @unless ($pallet->exists)
                <button name="another" value="1" class="btn btn-secondary">Guardar e ingresar otro</button>
            @endunless
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
