<x-layouts.app :title="$room->exists ? 'Editar cámara' : 'Nueva cámara'">
    <x-page-header :title="$room->exists ? 'Editar: '.$room->name : 'Nueva cámara'" :back="$room->exists ? route('cold-rooms.show', $room) : route('cold-rooms.index')"/>

    <form method="POST" action="{{ $room->exists ? route('cold-rooms.update', $room) : route('cold-rooms.store') }}" class="space-y-6">
        @csrf
        @if ($room->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="code" label="Código" :value="$room->code" class="code" required/>
                <x-input name="name" label="Nombre" :value="$room->name" required/>
                <x-select name="location_id" label="Ubicación en el galpón" :options="$locations" :value="$room->location_id" placeholder="—"/>
                <x-input name="temp_min" label="Temperatura mínima (°C)" :value="$room->temp_min" inputmode="decimal" required/>
                <x-input name="temp_max" label="Temperatura máxima (°C)" :value="$room->temp_max" inputmode="decimal" required/>
                <div></div>
                <x-input name="humidity_min" label="Humedad mínima (%)" :value="$room->humidity_min" inputmode="decimal"/>
                <x-input name="humidity_max" label="Humedad máxima (%)" :value="$room->humidity_max" inputmode="decimal"/>
                <x-input name="sensor_key" label="Clave de sensor (API)" :value="$room->sensor_key" class="code" hint="Para lecturas automáticas: POST /api/v1/cold-rooms/{clave}/readings"/>
                <div class="pt-6"><x-checkbox name="active" label="Activa" :checked="$room->active"/></div>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $room->exists ? route('cold-rooms.show', $room) : route('cold-rooms.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
