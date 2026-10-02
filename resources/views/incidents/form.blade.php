@php
    $relatedType = old('related_type', $incident->related_type);
    $relatedCode = old('related_code', match (true) {
        $incident->related instanceof \App\Models\Load => $incident->related->number,
        $incident->related !== null => $incident->related->code ?? null,
        default => null,
    });
@endphp
<x-layouts.app :title="$incident->exists ? 'Editar incidente' : 'Registrar incidente'">
    <x-page-header :title="$incident->exists ? 'Editar '.$incident->number : 'Registrar incidente'" :back="$incident->exists ? route('incidents.show', $incident) : route('incidents.index')"/>

    <form method="POST" action="{{ $incident->exists ? route('incidents.update', $incident) : route('incidents.store') }}" class="space-y-6">
        @csrf
        @if ($incident->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-2">
                <x-select name="type" label="Tipo" :options="\App\Models\Incident::TYPES" :value="$incident->type" required/>
                <x-select name="priority" label="Prioridad" :options="\App\Models\Incident::PRIORITIES" :value="$incident->priority" required hint="Alta y crítica generan una alerta."/>
                <x-input name="occurred_at" type="datetime-local" label="Fecha y hora" :value="$incident->occurred_at?->format('Y-m-d\TH:i')" required/>
                <x-input name="area" label="Sector" :value="$incident->area" maxlength="40" hint="Ej.: Línea 2, Playa de carga, Cámara 1"/>
                <x-select name="responsible_id" label="Responsable" :options="$users" :value="$incident->responsible_id" placeholder="Sin asignar"/>
                <div class="grid grid-cols-[140px_1fr] gap-2">
                    <x-select name="related_type" label="Vinculado a" :options="\App\Services\IncidentService::RELATED" :value="$relatedType" placeholder="Nada"/>
                    <x-input name="related_code" label="Código" :value="$relatedCode" class="code" maxlength="60" hint="Escaneá o escribí el código."/>
                </div>
                <div class="md:col-span-2"><x-textarea name="description" label="Descripción" :value="$incident->description" rows="4" required maxlength="5000"/></div>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $incident->exists ? route('incidents.show', $incident) : route('incidents.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
