<x-layouts.app title="Pallets">
    <x-page-header title="Ingreso de pallets" subtitle="Pallets recibidos, su origen, contenido y ubicación.">
        <x-slot:actions>
            @can('pallets.create')
                <a href="{{ route('pallets.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Ingresar pallet</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="q" label="Código" :value="request('q')" placeholder="PAL-… o código de barras" class="code"/>
        <x-input name="date_from" type="date" label="Desde" :value="request('date_from')"/>
        <x-input name="date_to" type="date" label="Hasta" :value="request('date_to')"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-select name="producer_id" label="Productor" :options="$producers" :value="request('producer_id')" placeholder="Todos"/>
        <x-select name="owner_id" label="Propietario" :options="$owners" :value="request('owner_id')" placeholder="Todos"/>
        <x-select name="variety_id" label="Variedad" :options="$varieties" :value="request('variety_id')" placeholder="Todas"/>
        <x-select name="lot_id" label="Lote" :options="$lots" :value="request('lot_id')" placeholder="Todos"/>
    </x-filters>

    <form method="GET" action="{{ route('labels.pallets') }}" target="_blank" x-data="{ selected: [] }">
        @can('labels.print')
            <div class="mb-2 flex items-center gap-2" x-show="selected.length" x-cloak>
                <button class="btn btn-secondary btn-sm"><x-icon name="printer" class="size-4"/> Imprimir etiquetas (<span x-text="selected.length"></span>)</button>
            </div>
        @endcan
        <x-table>
            <thead>
                <tr>
                    @can('labels.print')<th class="w-8"></th>@endcan
                    <th>Código</th><th>Ingreso</th><th>Lote</th><th>Productor</th><th>Propietario</th><th>Variedad</th>
                    <th class="num">Cajones</th><th>Ubicación</th><th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pallets as $pallet)
                    <tr>
                        @can('labels.print')
                            <td><input type="checkbox" name="ids[]" value="{{ $pallet->id }}" x-model="selected" class="size-4 rounded border-stone-300 text-brand-600" aria-label="Seleccionar {{ $pallet->code }}"></td>
                        @endcan
                        <td><a href="{{ route('pallets.show', $pallet) }}" class="code link">{{ $pallet->code }}</a></td>
                        <td class="tabular-nums whitespace-nowrap">{{ fdate($pallet->received_at, true) }}</td>
                        <td class="code">{{ $pallet->lot?->code ?? '—' }}</td>
                        <td>{{ $pallet->producer?->name ?? '—' }}</td>
                        <td>{{ $pallet->owner?->name ?? '—' }}</td>
                        <td>{{ $pallet->variety?->name ?? '—' }}</td>
                        <td class="num">{{ num($pallet->crates_count) }}</td>
                        <td>{{ $pallet->location?->name ?? '—' }}</td>
                        <td><x-status :status="$pallet->status"/></td>
                    </tr>
                @empty
                    <x-empty colspan="10"/>
                @endforelse
            </tbody>
            <x-slot:footer>{{ $pallets->links() }}</x-slot:footer>
        </x-table>
    </form>
</x-layouts.app>
