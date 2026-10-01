<x-layouts.app title="Lotes">
    <x-page-header title="Lotes" subtitle="Base de la trazabilidad: cada pallet y cajón se vincula a su lote de origen.">
        <x-slot:actions>
            @can('lots.manage')
                <a href="{{ route('lots.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo lote</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="q" label="Código" :value="request('q')" placeholder="LOT-…"/>
        <x-select name="producer_id" label="Productor" :options="$producers" :value="request('producer_id')" placeholder="Todos"/>
        <x-select name="owner_id" label="Propietario" :options="$owners" :value="request('owner_id')" placeholder="Todos"/>
        <x-select name="variety_id" label="Variedad" :options="$varieties" :value="request('variety_id')" placeholder="Todas"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Código</th><th>Fecha</th><th>Productor</th><th>Propietario</th><th>Variedad</th><th>Origen</th><th class="num">Cajones</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @forelse ($lots as $lot)
                <tr>
                    <td><a href="{{ route('lots.show', $lot) }}" class="code link">{{ $lot->code }}</a></td>
                    <td class="tabular-nums">{{ fdate($lot->date) }}</td>
                    <td>{{ $lot->producer?->name }}</td>
                    <td>{{ $lot->owner?->name ?? '—' }}</td>
                    <td>{{ $lot->variety?->name ?? '—' }}</td>
                    <td>{{ $lot->origin ?? '—' }}</td>
                    <td class="num">{{ num($lot->crates_count) }}</td>
                    <td><x-badge :color="['open' => 'emerald', 'closed' => 'blue', 'voided' => 'zinc'][$lot->status] ?? 'stone'">{{ \App\Models\Lot::STATUSES[$lot->status] ?? $lot->status }}</x-badge></td>
                    <td class="text-right"><a href="{{ route('lots.show', $lot) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="9"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $lots->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
