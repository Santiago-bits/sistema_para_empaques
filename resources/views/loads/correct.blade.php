<x-layouts.app :title="'Corregir carga '.$load->number">
    <x-page-header :title="'Corregir datos · carga '.$load->number"
                   :subtitle="$load->status->label().' · el contenido (cajones y kilos) no cambia. Para cambiar cajones hay que reabrir la carga.'"
                   :back="route('loads.show', $load)"/>

    <form method="POST" action="{{ route('loads.correct.save', $load) }}" class="max-w-5xl space-y-6">
        @csrf @method('PUT')
        <input type="hidden" name="date" value="{{ $load->date->toDateString() }}">
        <x-panel title="Transporte">
            <div class="grid gap-4 md:grid-cols-3">
                <x-select name="transporter_id" label="Transportista" :options="$transporters" :value="$load->transporter_id" placeholder="—"/>
                <x-select name="truck_id" label="Camión" :options="$trucks" :value="$load->truck_id" placeholder="—"/>
                <x-select name="driver_id" label="Camionero" :options="$drivers" :value="$load->driver_id" placeholder="—"/>
                <x-input name="trailer_plate" label="Patente del acoplado" :value="$load->trailer_plate" class="uppercase" maxlength="12"/>
                <x-input name="guide_number" label="N° de guía" :value="$load->guide_number" maxlength="40"/>
            </div>
            <p class="form-hint mt-3">Si el remito está emitido, toma el camión y el chofer corregidos.</p>
        </x-panel>
        <x-panel title="Datos comerciales">
            <div class="grid gap-4 md:grid-cols-4">
                <x-select name="commercial_destination" label="Destino comercial" :options="\App\Models\Load::COMMERCIAL_DESTINATIONS" :value="$load->commercial_destination" placeholder="—"/>
                <x-select name="sales_channel" label="Canal" :options="\App\Models\Load::SALES_CHANNELS" :value="$load->sales_channel" placeholder="—"/>
                <x-select name="sale_condition" label="Condición de venta" :options="\App\Models\Load::SALE_CONDITIONS" :value="$load->sale_condition" placeholder="—"/>
                <x-input name="freight_amount" inputmode="decimal" label="Flete ($)" :value="$load->freight_amount !== null ? num($load->freight_amount, 2) : null"
                         :hint="$load->freight_posted_at ? 'Ya imputado: si cambia, se corrige solo en la cuenta del transportista.' : null"/>
            </div>
            <div class="mt-4"><x-textarea name="notes" label="Observaciones" :value="$load->notes"/></div>
        </x-panel>
        <x-correction-reason/>
        <div class="flex justify-end gap-2">
            <a href="{{ route('loads.show', $load) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar corrección</button>
        </div>
    </form>
</x-layouts.app>
