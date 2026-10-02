@php
    $target = $control->crate ? 'cajón '.$control->crate->code : ($control->pallet ? 'pallet '.$control->pallet->code : ($control->lot ? 'lote '.$control->lot->code : ''));
@endphp
<x-layouts.app title="Corregir control de calidad">
    <x-page-header :title="'Corregir control · '.$target" subtitle="Cambiá lo que se cargó mal: no hace falta borrar ni volver a hacer el control."
                   :back="route('quality.show', $control)"/>

    <form method="POST" action="{{ route('quality.update', $control) }}" class="max-w-4xl space-y-6">
        @csrf @method('PUT')
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-select name="result" label="Resultado" :options="\App\Models\QualityControl::RESULTS" :value="$control->result" required
                          :hint="$control->crate_id ? 'Si cambia, se actualiza también el estado del cajón.' : null"/>
                <x-input name="controlled_at" type="datetime-local" label="Fecha y hora" :value="$control->controlled_at" required/>
                <x-input name="grade" label="Calidad" :value="$control->grade" maxlength="60"/>
                <x-input name="caliber" label="Calibre" :value="$control->caliber" maxlength="60"/>
                <x-input name="ripeness" label="Maduración" :value="$control->ripeness" maxlength="60"/>
                <div></div>
                <x-input name="damage_pct" inputmode="decimal" label="% daños" :value="num($control->damage_pct, 1)"/>
                <x-input name="bruise_pct" inputmode="decimal" label="% golpes" :value="num($control->bruise_pct, 1)"/>
                <x-input name="rot_pct" inputmode="decimal" label="% podredumbre" :value="num($control->rot_pct, 1)"/>
                <x-input name="reject_pct" inputmode="decimal" label="% rechazo" :value="num($control->reject_pct, 1)"/>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-textarea name="defects" label="Defectos" :value="$control->defects"/>
                <x-textarea name="notes" label="Observaciones" :value="$control->notes"/>
            </div>
        </x-panel>
        <x-correction-reason/>
        <div class="flex justify-end gap-2">
            <a href="{{ route('quality.show', $control) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar corrección</button>
        </div>
    </form>
</x-layouts.app>
