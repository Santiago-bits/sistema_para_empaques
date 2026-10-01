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
                <x-select name="driver_id" label="Chofer" :options="$drivers" :value="$load->driver_id" placeholder="—"/>
                <x-input name="planned_crates" type="number" min="1" label="Cajones previstos" :value="$load->planned_crates" hint="Opcional: muestra el avance del armado."/>
            </div>
            <div class="mt-4"><x-textarea name="notes" label="Observaciones" :value="$load->notes"/></div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $load->exists ? route('loads.show', $load) : route('loads.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">{{ $load->exists ? 'Guardar' : 'Crear y armar' }}</button>
        </div>
    </form>
</x-layouts.app>
