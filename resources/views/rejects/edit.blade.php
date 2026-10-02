<x-layouts.app title="Corregir rechazo">
    <x-page-header title="Corregir rechazo"
                   :subtitle="trim(($reject->crate ? 'Cajón '.$reject->crate->code.' · ' : '').($reject->lot ? 'Lote '.$reject->lot->code.' · ' : '').fdate($reject->rejected_at, true))"
                   :back="route('rejects.index')"/>

    <form method="POST" action="{{ route('rejects.update', $reject) }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-select name="reason_id" label="Motivo de rechazo" :options="$reasons" :value="$reject->reason_id" required/>
                <x-input name="weight" inputmode="decimal" label="Peso rechazado (kg)" :value="num($reject->weight, 2)" required/>
                <x-input name="rejected_at" type="datetime-local" label="Fecha y hora" :value="$reject->rejected_at" required/>
            </div>
            <div class="mt-4"><x-textarea name="notes" label="Observaciones" :value="$reject->notes"/></div>
        </x-panel>
        <x-correction-reason/>
        <div class="flex justify-end gap-2">
            <a href="{{ route('rejects.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar corrección</button>
        </div>
    </form>
</x-layouts.app>
