@php
    $breakdowns = [
        'Por variedad' => $byVariety,
        'Por lote (top 10)' => $byLot,
        'Por embalador (top 10)' => $byPacker,
        'Por motivo' => $byReason,
    ];
@endphp
<x-layouts.app title="Rechazos y merma">
    <x-page-header title="Rechazos y merma" :subtitle="'Período '.fdate($from).' al '.fdate($to)">
        <x-slot:actions>
            @can('quality.manage')
                <a href="{{ route('rejects.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Registrar rechazo</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="from" type="date" label="Desde" :value="$from->toDateString()"/>
        <x-input name="to" type="date" label="Hasta" :value="$to->toDateString()"/>
        <x-select name="variety_id" label="Variedad" :options="$varieties" :value="request('variety_id')" placeholder="Todas"/>
        <x-select name="size_id" label="Tamaño" :options="$sizes" :value="request('size_id')" placeholder="Todos"/>
        <x-select name="lot_id" label="Lote" :options="$lots" :value="request('lot_id')" placeholder="Todos"/>
        <x-select name="packer_id" label="Embalador" :options="$packers" :value="request('packer_id')" placeholder="Todos"/>
        <x-select name="reason_id" label="Motivo" :options="$reasons" :value="request('reason_id')" placeholder="Todos"/>
    </x-filters>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-6">
        <x-stat label="Kg ingresados" :value="kg($summary['received_kg'], 0)" icon="pallet" color="stone"/>
        <x-stat label="Kg procesados" :value="kg($summary['processed_kg'], 0)" icon="box"/>
        <x-stat label="Kg rechazados" :value="kg($summary['rejected_kg'], 0)" icon="trash" color="red" :hint="num($summary['rejected_count']).' rechazos'"/>
        <x-stat label="% merma" :value="pct($summary['waste_pct'])" icon="chart-line" color="amber"/>
        <x-stat label="Kg despachados" :value="kg($summary['dispatched_kg'], 0)" icon="truck" color="sky"/>
        <x-stat label="% aprovechamiento" :value="$summary['utilization_pct'] !== null ? pct($summary['utilization_pct']) : '—'" icon="check-badge" color="violet"/>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-panel title="Merma por variedad (kg)">
            @if ($byVariety)<div class="h-64"><canvas id="chart-variety" aria-label="Merma por variedad"></canvas></div>@else<p class="py-16 text-center text-sm text-stone-500">Sin rechazos en el período.</p>@endif
        </x-panel>
        <x-panel title="Merma por motivo (kg)">
            @if ($byReason)<div class="h-64"><canvas id="chart-reason" aria-label="Merma por motivo"></canvas></div>@else<p class="py-16 text-center text-sm text-stone-500">Sin rechazos en el período.</p>@endif
        </x-panel>
    </div>

    <div class="mb-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($breakdowns as $title => $rows)
            <x-panel :title="$title" :padding="false">
                <table class="table">
                    <thead><tr><th></th><th class="num">Kg</th><th class="num">% merma</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="max-w-40 truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</td>
                                <td class="num">{{ num($row['kg'], 1) }}</td>
                                <td class="num">{{ ($row['processed_kg'] ?? 0) > 0 ? pct($row['pct']) : '—' }}</td>
                            </tr>
                        @empty
                            <x-empty colspan="3" message="Sin datos."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
        @endforeach
    </div>

    <x-table>
        <thead><tr><th>Fecha</th><th>Cajón</th><th>Lote</th><th>Variedad</th><th>Tamaño</th><th>Embalador</th><th>Motivo</th><th class="num">Kg</th><th>Responsable</th><th></th></tr></thead>
        <tbody>
            @forelse ($rejects as $reject)
                <tr>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($reject->rejected_at, true) }}</td>
                    <td class="code">{{ $reject->crate?->code ?? '—' }}</td>
                    <td class="code">{{ $reject->lot?->code ?? '—' }}</td>
                    <td>{{ $reject->variety?->name ?? '—' }}</td>
                    <td>{{ $reject->size?->name ?? '—' }}</td>
                    <td>{{ $reject->packer?->code ?? '—' }}</td>
                    <td>{{ $reject->reason?->name }}</td>
                    <td class="num">{{ num($reject->weight, 2) }}</td>
                    <td>{{ $reject->user?->full_name }}</td>
                    <td class="text-right">@can('quality.manage')<a href="{{ route('rejects.edit', $reject) }}" class="link">Corregir</a>@endcan</td>
                </tr>
            @empty
                <x-empty colspan="10"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rejects->links() }}</x-slot:footer>
    </x-table>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = {{ \Illuminate\Support\Js::from($charts) }};
                const make = (id, type, set) => {
                    const el = document.getElementById(id);
                    if (!el || !window.Chart) return;
                    new Chart(el, {
                        type,
                        data: { labels: set.labels, datasets: [{ data: set.kg, backgroundColor: window.chartColors, borderWidth: 0, borderRadius: type === 'bar' ? 6 : 0 }] },
                        options: { plugins: { legend: { display: type !== 'bar', position: 'right' } }, scales: type === 'bar' ? { y: { beginAtZero: true } } : {} },
                    });
                };
                make('chart-variety', 'bar', data.variety);
                make('chart-reason', 'doughnut', data.reason);
            });
        </script>
    @endpush
</x-layouts.app>
