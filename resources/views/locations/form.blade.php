<x-layouts.app :title="$location->exists ? 'Editar ubicación' : 'Nueva ubicación'">
    <x-page-header :title="$location->exists ? 'Editar: '.$location->name : 'Nueva ubicación'"
                   :back="$location->exists ? route('locations.show', $location) : route('locations.index')"/>

    <form method="POST" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}" class="space-y-6">
        @csrf
        @if ($location->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-select name="type" label="Tipo" :options="$types" :value="$location->type" required/>
                <x-input name="code" label="Código" :value="$location->code" class="code uppercase" required hint="Ej.: S-A, A01, CAM-1. Se puede escanear."/>
                <x-input name="name" label="Nombre" :value="$location->name" required/>
                <x-select name="parent_id" label="Dentro de" :options="$parents" :value="$location->parent_id" placeholder="(nivel superior del galpón)"/>
                <x-input name="capacity_pallets" type="number" min="0" label="Capacidad (pallets)" :value="$location->capacity_pallets" hint="0 = suma de las sububicaciones."/>
                <div class="pt-6"><x-checkbox name="active" label="Activa" :checked="$location->active"/></div>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $location->exists ? route('locations.show', $location) : route('locations.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
