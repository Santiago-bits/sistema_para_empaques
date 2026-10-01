@php
    $editing = $crate->exists;
    $show = fn (string $field) => ($modes[$field] ?? 'optional') !== 'hidden';
    $req = fn (string $field) => ($modes[$field] ?? 'optional') === 'required';
@endphp
<x-layouts.app :title="$editing ? 'Editar cajón' : 'Nuevo cajón'">
    <x-page-header :title="$editing ? 'Editar cajón '.$crate->code : 'Nuevo cajón'" :back="$editing ? route('crates.show', $crate) : route('crates.index')"/>

    <form method="POST" action="{{ $editing ? route('crates.update', $crate) : route('crates.store') }}" class="space-y-6" x-data="{ process: {{ old('process') ? 'true' : 'false' }} }">
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="version" value="{{ $crate->version }}">
        @endif

        <x-panel title="Identificación y origen">
            <div class="grid gap-4 md:grid-cols-3">
                @if ($editing)
                    <x-field label="Código"><p class="code py-2 text-lg">{{ $crate->code }}</p></x-field>
                @else
                    <x-input name="code" label="Código" class="code" :placeholder="$nextCode" autofocus hint="Vacío = numeración automática."/>
                @endif
                @if ($show('barcode'))<x-input name="barcode" label="Código de barras" :value="$crate->barcode" class="code" :required="$req('barcode')"/>@endif
                @if ($show('pallet_id'))<x-select name="pallet_id" label="Pallet" :options="$pallets" :value="$crate->pallet_id" placeholder="—" :required="$req('pallet_id')"/>@endif
                @if ($show('lot_id'))<x-select name="lot_id" label="Lote" :options="$lots" :value="$crate->lot_id" placeholder="Del pallet" :required="$req('lot_id')"/>@endif
                @if ($show('location_id'))<x-select name="location_id" label="Ubicación" :options="$locations" :value="$crate->location_id" placeholder="—" :required="$req('location_id')"/>@endif
            </div>
        </x-panel>

        <x-panel title="Producción">
            @unless ($editing)
                <div class="mb-4">
                    <x-checkbox name="process" label="Registrar la producción ahora" no-hidden x-model="process"
                                hint="Aplica las mismas validaciones que el modo escaneo (peso permitido, embalador activo)."/>
                </div>
            @endunless
            <div class="grid gap-4 md:grid-cols-4">
                @if ($show('packer_id'))<x-select name="packer_id" label="Embalador" :options="$packers" :value="$crate->packer_id" placeholder="—" :required="$req('packer_id')"/>@endif
                @if ($show('variety_id'))<x-select name="variety_id" label="Variedad" :options="$varieties" :value="$crate->variety_id" placeholder="—" :required="$req('variety_id')"/>@endif
                @if ($show('size_id'))<x-select name="size_id" label="Tamaño" :options="$sizes" :value="$crate->size_id" placeholder="—" :required="$req('size_id')"/>@endif
                @if ($show('weight'))<x-input name="weight" label="Peso (kg)" :value="$crate->weight" inputmode="decimal" :required="$req('weight')"/>@endif
            </div>
            @if ($editing)
                <div class="mt-4 rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30">
                    <x-input name="reason" label="Motivo del cambio" hint="Obligatorio si cambiás peso, variedad, tamaño o embalador. Queda en la auditoría con el valor anterior y el nuevo."/>
                </div>
            @endif
        </x-panel>

        @if ($show('notes'))
            <x-panel title="Observaciones">
                <x-textarea name="notes" :value="$crate->notes" :required="$req('notes')"/>
            </x-panel>
        @endif

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ $editing ? route('crates.show', $crate) : route('crates.index') }}" class="btn btn-secondary">Cancelar</a>
            @unless ($editing)
                <button name="another" value="1" class="btn btn-secondary">Guardar y cargar otro</button>
            @endunless
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
