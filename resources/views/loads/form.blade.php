<x-layouts.app :title="$load->exists ? 'Editar carga' : 'Nueva carga'">
    <x-page-header :title="$load->exists ? 'Editar carga '.$load->number : 'Nueva carga'" :back="$load->exists ? route('loads.show', $load) : route('loads.index')"/>

    <form method="POST" action="{{ $load->exists ? route('loads.update', $load) : route('loads.store') }}" class="space-y-6"
          x-data="{ filterDestinations(clientId) {
              const sel = this.$refs.destination;
              [...sel.options].forEach(o => { if (o.value) o.hidden = !!clientId && !!o.dataset.client && o.dataset.client !== String(clientId); });
              if (sel.selectedOptions[0] && sel.selectedOptions[0].hidden) sel.value = '';
              const visible = [...sel.options].filter(o => o.value && !o.hidden);
              if (!sel.value && clientId && visible.length === 1) sel.value = visible[0].value;
          } }"
          x-init="filterDestinations(document.getElementById('client_id').value)"
          @change="if ($event.target.name === 'client_id') filterDestinations($event.target.value)">
        @csrf
        @if ($load->exists) @method('PUT') @endif
        <x-panel title="Destino y transporte">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="date" type="date" label="Fecha" :value="$load->date" required/>
                <x-select name="client_id" label="Cliente" :options="$clients" :value="$load->client_id" placeholder="—" hint="Quién recibe la mercadería."/>
                <x-field label="Destino" name="destination_id" hint="Se muestran los destinos del cliente elegido.">
                    <select name="destination_id" id="destination_id" class="form-input" x-ref="destination">
                        <option value="">—</option>
                        @foreach ($destinations as $id => $name)
                            <option value="{{ $id }}" data-client="{{ $destinationClients[$id] ?? '' }}" @selected((string) old('destination_id', $load->destination_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-select name="owner_id" label="Propietario" :options="$owners" :value="$load->owner_id" placeholder="—"/>
                <x-select name="transporter_id" label="Transportista" :options="$transporters" :value="$load->transporter_id" placeholder="—"/>
                <x-select name="truck_id" label="Camión" :options="$trucks" :value="$load->truck_id" placeholder="—"/>
                <x-select name="driver_id" label="Camionero" :options="$drivers" :value="$load->driver_id" placeholder="—"/>
                <x-input name="planned_crates" type="number" min="1" label="Cajones previstos" :value="$load->planned_crates" hint="Opcional: muestra el avance del armado."/>
                <x-input name="trailer_plate" label="Patente del acoplado" :value="$load->trailer_plate" class="uppercase" maxlength="12" placeholder="AA123BB"/>
                <x-input name="guide_number" label="N° de guía" :value="$load->guide_number" maxlength="40" hint="Guía de tránsito / DTV."/>
            </div>
            <h3 class="mt-6 mb-2 text-sm font-semibold text-stone-700 dark:text-stone-300">Datos comerciales</h3>
            <div class="grid gap-4 md:grid-cols-4">
                <x-select name="commercial_destination" label="Destino comercial" :options="\App\Models\Load::COMMERCIAL_DESTINATIONS" :value="$load->commercial_destination" placeholder="—"/>
                <x-select name="sales_channel" label="Canal de comercialización" :options="\App\Models\Load::SALES_CHANNELS" :value="$load->sales_channel" placeholder="—"/>
                <x-select name="sale_condition" label="Condición de venta" :options="\App\Models\Load::SALE_CONDITIONS" :value="$load->sale_condition" placeholder="—"/>
                <x-input name="freight_amount" inputmode="decimal" label="Flete ($)" :value="$load->freight_amount !== null ? num($load->freight_amount, 2) : null"
                         hint="Al despachar, se le acredita al transportista en su cuenta corriente."/>
            </div>
            <div class="mt-4"><x-textarea name="notes" label="Observaciones" :value="$load->notes"/></div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $load->exists ? route('loads.show', $load) : route('loads.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">{{ $load->exists ? 'Guardar' : 'Crear y armar' }}</button>
        </div>
    </form>
</x-layouts.app>
