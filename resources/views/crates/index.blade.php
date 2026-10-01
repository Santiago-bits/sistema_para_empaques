<x-layouts.app title="Cajones">
    <x-page-header title="Cajones" subtitle="Cada cajón es la unidad de trazabilidad del sistema.">
        <x-slot:actions>
            @can('labels.print')
                <a href="{{ route('labels.crates') }}" class="btn btn-secondary"><x-icon name="printer" class="size-4"/> Etiquetas</a>
            @endcan
            @can('crates.create')
                <a href="{{ route('crates.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo cajón</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="code" label="Código" :value="request('code')" class="code" placeholder="CJ-…"/>
        <x-input name="date_from" type="date" label="Alta desde" :value="request('date_from')"/>
        <x-input name="date_to" type="date" label="Alta hasta" :value="request('date_to')"/>
        <x-select name="variety_id" label="Variedad" :options="$varieties" :value="request('variety_id')" placeholder="Todas"/>
        <x-select name="size_id" label="Tamaño" :options="$sizes" :value="request('size_id')" placeholder="Todos"/>
        <x-select name="packer_id" label="Embalador" :options="$packers" :value="request('packer_id')" placeholder="Todos"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-select name="quality_status" label="Calidad" :options="$qualities" :value="request('quality_status')" placeholder="Todas"/>
        <x-select name="lot_id" label="Lote" :options="$lots" :value="request('lot_id')" placeholder="Todos"/>
        <x-select name="producer_id" label="Productor" :options="$producers" :value="request('producer_id')" placeholder="Todos"/>
        <x-select name="owner_id" label="Propietario" :options="$owners" :value="request('owner_id')" placeholder="Todos"/>
        <x-input name="pallet" label="Pallet" :value="request('pallet')" class="code" placeholder="PAL-…"/>
        <x-input name="load" label="Carga" :value="request('load')" class="code" placeholder="CARG-…"/>
    </x-filters>

    <div class="mb-3 flex flex-wrap items-center gap-4 text-sm text-stone-600 dark:text-stone-400">
        <span><strong class="tabular-nums text-stone-900 dark:text-white">{{ num($totals->crates) }}</strong> cajones</span>
        <span><strong class="tabular-nums text-stone-900 dark:text-white">{{ kg($totals->kg, 1) }}</strong> en total</span>
        @can('labels.print')
            <a href="{{ route('labels.crates', array_merge(request()->except('page', 'per_page'), ['filtered' => 1])) }}" target="_blank" class="link">Imprimir etiquetas de este filtro</a>
        @endcan
    </div>

    <x-table>
        <thead>
            <tr><th>Código</th><th>Alta</th><th>Lote</th><th>Pallet</th><th>Variedad</th><th>Tamaño</th><th>Embalador</th><th class="num">Peso</th><th>Estado</th><th>Calidad</th></tr>
        </thead>
        <tbody>
            @forelse ($crates as $crate)
                <tr>
                    <td><a href="{{ route('crates.show', $crate) }}" class="code link">{{ $crate->code }}</a></td>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($crate->created_at, true) }}</td>
                    <td class="code">{{ $crate->lot?->code ?? '—' }}</td>
                    <td class="code">{{ $crate->pallet?->code ?? '—' }}</td>
                    <td>{{ $crate->variety?->name ?? '—' }}</td>
                    <td>{{ $crate->size?->name ?? '—' }}</td>
                    <td>{{ $crate->packer?->code ?? '—' }}</td>
                    <td class="num">{{ $crate->weight !== null ? num($crate->weight, 2) : '—' }}</td>
                    <td><x-status :status="$crate->status"/></td>
                    <td><x-badge :color="['approved' => 'emerald', 'rejected' => 'red'][$crate->quality_status] ?? 'stone'">{{ $qualities[$crate->quality_status] ?? $crate->quality_status }}</x-badge></td>
                </tr>
            @empty
                <x-empty colspan="10"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $crates->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
