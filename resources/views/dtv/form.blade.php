@php
    $editing = $document->exists;
    $initialLines = collect(old('lines', $lines))->map(fn ($l) => [
        'species' => $l['species'] ?? '', 'variety_id' => (string) ($l['variety_id'] ?? ''), 'variety_name' => $l['variety_name'] ?? '',
        'quantity' => isset($l['quantity']) && $l['quantity'] !== '' ? (string) (float) $l['quantity'] : '', 'unit' => $l['unit'] ?? 'Cajón',
        'kg_per_unit' => isset($l['kg_per_unit']) && $l['kg_per_unit'] !== null && $l['kg_per_unit'] !== '' ? (string) (float) $l['kg_per_unit'] : '',
        'kg_total' => isset($l['kg_total']) && $l['kg_total'] !== null && $l['kg_total'] !== '' ? (string) (float) $l['kg_total'] : '',
    ])->values()->all();
    $varietyList = $varieties->map(fn ($v) => ['id' => (string) $v->id, 'name' => $v->name, 'species' => $v->species])->values()->all();
    $title = $editing ? 'Corregir DTV-e '.$document->number : ($document->direction === 'in' ? 'Nuevo DTV-e de ingreso' : 'Nuevo DTV-e de egreso');
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" subtitle="Una línea por especie y variedad. Los kilos totales se calculan solos (cantidad × kg)." :back="$editing ? route('dtv.show', $document) : route('dtv.index')"/>

    <form method="POST" action="{{ $editing ? route('dtv.update', $document) : route('dtv.store') }}" class="space-y-6"
          x-data="dtvForm({{ \Illuminate\Support\Js::from(['lines' => $initialLines, 'varieties' => $varietyList]) }})">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="load_id" value="{{ old('load_id', $document->load_id) }}">
        <input type="hidden" name="lot_id" value="{{ old('lot_id', $document->lot_id) }}">

        <x-panel title="Datos del documento">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-input name="date" type="date" label="Fecha" :value="old('date', $document->date?->toDateString() ?? $document->date)" required/>
                <x-select name="direction" label="Ingreso o egreso" :options="\App\Models\DtvDocument::DIRECTIONS" :value="old('direction', $document->direction)" required/>
                <x-input name="number" label="N° DTV-e" :value="old('number', $document->number)" required class="code" placeholder="14606819-7"/>
                <x-select name="doc_type" label="Tipo" :options="$docTypes" :value="old('doc_type', $document->doc_type)" placeholder="—"/>
                <x-input name="issuer" label="Emisor" :value="old('issuer', $document->issuer)"/>
                <x-input name="establishment" label="Establecimiento" :value="old('establishment', $document->establishment)" placeholder="E-2929-b-C"/>
                <x-input name="recipient" label="Destinatario" :value="old('recipient', $document->recipient)"/>
                <x-input name="destination" label="Destino" :value="old('destination', $document->destination)"/>
                <x-input name="transport" label="Transporte (camionero o empresa)" :value="old('transport', $document->transport)" class="sm:col-span-2"/>
                <x-input name="notes" label="Observaciones" :value="old('notes', $document->notes)" class="sm:col-span-2"/>
            </div>
        </x-panel>

        <x-panel title="Líneas (especie y variedad)">
            @error('lines')<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="space-y-3">
                <template x-for="(line, i) in lines" :key="i">
                    <div class="grid gap-2 rounded-lg border border-stone-200 p-3 sm:grid-cols-6 lg:grid-cols-[repeat(7,minmax(0,1fr))_auto] dark:border-stone-800">
                        <label class="block text-xs font-medium text-stone-500 lg:col-span-2">Variedad
                            <select class="form-input mt-1 w-full" :name="`lines[${i}][variety_id]`" x-model="line.variety_id" @change="pickVariety(line)">
                                <option value="">— Escribir a mano —</option>
                                <template x-for="v in varieties" :key="v.id"><option :value="v.id" x-text="v.name" :selected="v.id === line.variety_id"></option></template>
                            </select>
                            <input x-show="!line.variety_id" class="form-input mt-1 w-full" :name="`lines[${i}][variety_name]`" x-model="line.variety_name" placeholder="Ej.: Lane late">
                        </label>
                        <label class="block text-xs font-medium text-stone-500">Especie
                            <input class="form-input mt-1 w-full" :name="`lines[${i}][species]`" x-model="line.species" placeholder="Naranja">
                        </label>
                        <label class="block text-xs font-medium text-stone-500">Cantidad
                            <input class="form-input mt-1 w-full" inputmode="decimal" :name="`lines[${i}][quantity]`" x-model="line.quantity" placeholder="108">
                        </label>
                        <label class="block text-xs font-medium text-stone-500">Unidad
                            <input class="form-input mt-1 w-full" :name="`lines[${i}][unit]`" x-model="line.unit" list="dtv-units">
                        </label>
                        <label class="block text-xs font-medium text-stone-500">Kg por unidad
                            <input class="form-input mt-1 w-full" inputmode="decimal" :name="`lines[${i}][kg_per_unit]`" x-model="line.kg_per_unit" placeholder="18">
                        </label>
                        <label class="block text-xs font-medium text-stone-500">Kg totales
                            <input x-show="!line.kg_per_unit" class="form-input mt-1 w-full" inputmode="decimal" :name="`lines[${i}][kg_total]`" x-model="line.kg_total">
                            <span x-show="line.kg_per_unit" class="form-input mt-1 block w-full bg-stone-50 tabular-nums dark:bg-stone-800/60" x-text="total(line)"></span>
                        </label>
                        <div class="flex items-end">
                            <button type="button" class="btn btn-ghost btn-sm text-red-600" @click="lines.splice(i, 1)" x-show="lines.length > 1" aria-label="Quitar línea"><x-icon name="trash" class="size-4"/></button>
                        </div>
                    </div>
                </template>
            </div>
            <datalist id="dtv-units"><option value="Cajón"><option value="Bin"><option value="Bulto"><option value="Kg"></datalist>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <button type="button" class="btn btn-secondary btn-sm" @click="lines.push({ species: '', variety_id: '', variety_name: '', quantity: '', unit: lines[lines.length - 1]?.unit || 'Cajón', kg_per_unit: lines[lines.length - 1]?.kg_per_unit || '', kg_total: '' })">
                    <x-icon name="plus" class="size-4"/> Agregar línea
                </button>
                <p class="text-sm">Total: <strong class="tabular-nums" x-text="grandTotal()"></strong> kg</p>
            </div>
        </x-panel>

        @if ($editing)
            <x-correction-reason/>
        @endif

        <div class="flex justify-end gap-2">
            <a href="{{ $editing ? route('dtv.show', $document) : route('dtv.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4"/> Guardar</button>
        </div>
    </form>

    <script>
        function dtvForm(config) {
            const parse = (v) => {
                if (v === null || v === undefined || v === '') return 0;
                const s = String(v).trim();
                // 1.234,5 → 1234.5 ; 18.5 → 18.5
                const normalized = s.includes(',') ? s.replace(/\./g, '').replace(',', '.') : s;
                const n = parseFloat(normalized);
                return Number.isFinite(n) ? n : 0;
            };
            return {
                lines: config.lines.length ? config.lines : [{ species: '', variety_id: '', variety_name: '', quantity: '', unit: 'Cajón', kg_per_unit: '', kg_total: '' }],
                varieties: config.varieties,
                pickVariety(line) {
                    const v = this.varieties.find((x) => x.id === line.variety_id);
                    if (v && !line.species) line.species = v.species || '';
                },
                total(line) {
                    return (Math.round(parse(line.quantity) * parse(line.kg_per_unit) * 100) / 100).toString();
                },
                grandTotal() {
                    const sum = this.lines.reduce((acc, l) => acc + (l.kg_per_unit ? parse(l.quantity) * parse(l.kg_per_unit) : parse(l.kg_total)), 0);
                    return sum.toLocaleString('es-AR', { maximumFractionDigits: 2 });
                },
            };
        }
    </script>
</x-layouts.app>
