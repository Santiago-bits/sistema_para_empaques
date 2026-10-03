@php $editing = $yield->exists; @endphp
<x-layouts.app :title="$editing ? 'Rendimiento de '.$yield->name : 'Nuevo tambor / insumo'">
    <x-page-header :title="$editing ? $yield->name : 'Nuevo tambor / insumo'" subtitle="Cargá la fecha en que lo empezaste a usar; cuando se termine, volvé y poné la fecha final." :back="route('yields.index')"/>

    <form method="POST" action="{{ $editing ? route('yields.update', $yield) : route('yields.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" label="Nombre" :value="old('name', $yield->name)" required placeholder="Tambor de cera N° 3"/>
                <x-select name="supply_id" label="Insumo (opcional)" :options="$supplies" :value="old('supply_id', $yield->supply_id)" placeholder="—"/>
                <x-input name="started_on" type="date" label="Desde" :value="old('started_on', $yield->started_on?->toDateString())" required/>
                <x-input name="ended_on" type="date" label="Hasta" :value="old('ended_on', $yield->ended_on?->toDateString())" hint="Vacío mientras se sigue usando."/>
                <div class="grid grid-cols-3 gap-3">
                    <x-input name="quantity_used" label="Cantidad usada" inputmode="decimal" :value="old('quantity_used', $yield->quantity_used !== null ? (float) $yield->quantity_used : null)" class="col-span-2" hint="Ej.: 200"/>
                    <x-input name="unit" label="Unidad" :value="old('unit', $yield->unit)" placeholder="litros"/>
                </div>
                <x-input name="packages_manual" type="number" min="0" label="Bultos (sólo si querés cargarlos a mano)" :value="old('packages_manual', $yield->packages_manual)"
                         :hint="'Si lo dejás vacío se cuentan solos: '.num($yield->exists ? $yield->packages() : 0).' bultos hasta ahora.'"/>
                <x-input name="notes" label="Observaciones" :value="old('notes', $yield->notes)" class="sm:col-span-2"/>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ route('yields.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4"/> Guardar</button>
        </div>
    </form>
</x-layouts.app>
