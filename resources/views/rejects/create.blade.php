<x-layouts.app title="Registrar rechazo">
    <x-page-header title="Registrar rechazo (merma)" subtitle="Indicá el cajón (toma sus datos) o completá variedad, lote y peso a mano." :back="route('rejects.index')"/>

    <form method="POST" action="{{ route('rejects.store') }}" class="space-y-6">
        @csrf
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="crate_code" label="Cajón (opcional)" class="code" autofocus placeholder="Escanear código"/>
                <x-select name="reason_id" label="Motivo" :options="$reasons" placeholder="Seleccionar…" required/>
                <x-input name="weight" label="Peso rechazado (kg)" inputmode="decimal" hint="Vacío = peso del cajón."/>
                <x-select name="variety_id" label="Variedad" :options="$varieties" placeholder="—"/>
                <x-select name="size_id" label="Tamaño" :options="$sizes" placeholder="—"/>
                <x-select name="lot_id" label="Lote" :options="$lots" placeholder="—"/>
                <x-select name="packer_id" label="Embalador" :options="$packers" placeholder="—"/>
                <x-input name="rejected_at" type="datetime-local" label="Fecha y hora" hint="Vacío = ahora."/>
            </div>
            <div class="mt-4"><x-textarea name="notes" label="Observaciones"/></div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ route('rejects.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Registrar rechazo</button>
        </div>
    </form>
</x-layouts.app>
