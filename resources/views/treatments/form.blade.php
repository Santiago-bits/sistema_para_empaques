@php $editing = $treatment->exists; @endphp
<x-layouts.app :title="$editing ? 'Corregir tratamiento' : 'Nuevo tratamiento'">
    <x-page-header :title="$editing ? 'Corregir tratamiento' : 'Nuevo tratamiento'" :back="route('treatments.index')"/>

    <form method="POST" action="{{ $editing ? route('treatments.update', $treatment) : route('treatments.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="date" type="date" label="Fecha" :value="old('date', $treatment->date?->toDateString())" required/>
                <x-select name="client_id" label="Cliente" :options="$clients" :value="old('client_id', $treatment->client_id)" placeholder="—"/>
                <x-input name="destination" label="Destino" :value="old('destination', $treatment->destination)" list="treatment-destinations" placeholder="Neuquén"/>
                <div class="grid grid-cols-3 gap-3">
                    <x-input name="quantity" label="Cantidad" inputmode="decimal" :value="old('quantity', $treatment->quantity !== null ? (float) $treatment->quantity : null)" required class="col-span-2"/>
                    <x-input name="unit" label="Unidad" :value="old('unit', $treatment->unit)" list="treatment-units" required/>
                </div>
                <x-field label="Tipo de tratamiento" name="type" :required="true">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($types as $type)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-stone-300 px-3 py-2 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 dark:border-stone-700 dark:has-[:checked]:bg-brand-950/40">
                                <input type="radio" name="type" value="{{ $type }}" @checked(old('type', $treatment->type) === $type) class="text-brand-600"> {{ $type }}
                            </label>
                        @endforeach
                    </div>
                </x-field>
                <x-input name="provider" label="Empresa que hace el tratamiento" :value="old('provider', $treatment->provider)" list="treatment-providers" placeholder="Bromex"/>
                <x-input name="dtv_number" label="N° de DTV-e (opcional)" :value="old('dtv_number', $treatment->dtv_number)" class="code"/>
                <x-input name="notes" label="Observaciones" :value="old('notes', $treatment->notes)"/>
            </div>
            <datalist id="treatment-destinations">@foreach ($destinations as $d)<option value="{{ $d }}">@endforeach<option value="Neuquén"><option value="Puerto Madryn"></datalist>
            <datalist id="treatment-providers">@foreach ($providers as $p)<option value="{{ $p }}">@endforeach</datalist>
            <datalist id="treatment-units"><option value="Cajón"><option value="Bin"><option value="Bulto"><option value="Kg"></datalist>
        </x-panel>

        @if ($editing)
            <x-correction-reason/>
        @endif

        <div class="flex justify-end gap-2">
            <a href="{{ route('treatments.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4"/> Guardar</button>
        </div>
    </form>

    @if ($editing)
        @can('treatments.manage')
            <div class="mt-6 max-w-3xl"><x-void-button :action="route('treatments.destroy', $treatment)" label="Eliminar este tratamiento" title="¿Eliminar este tratamiento?"/></div>
        @endcan
    @endif
</x-layouts.app>
