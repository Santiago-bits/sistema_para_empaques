@php
    use App\Services\Reports\Format;
    $labels = ['variety_id' => 'Variedad', 'size_id' => 'Tamaño', 'packer_id' => 'Embalador', 'producer_id' => 'Productor', 'client_id' => 'Cliente',
        'destination_id' => 'Destino', 'shift_id' => 'Turno', 'production_line_id' => 'Línea', 'reason_id' => 'Motivo', 'status' => 'Estado', 'group' => 'Agrupar por'];
    $query = request()->except(['format', 'variant', 'page']);
    $exportUrl = fn ($format, $variant) => route('reports.show', $report).'?'.http_build_query(array_merge($query, ['format' => $format, 'variant' => $variant]));
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$packer ? 'Informe de embalador · '.$packer->code.' — '.$packer->full_name : $title" :subtitle="collect($filters->describe())->map(fn ($v, $k) => $k.': '.$v)->join(' · ')" :back="route('reports.index')">
        <x-slot:actions>
            @foreach ($variants as $variant => $variantLabel)
                <div x-data="{ open: false }" class="relative">
                    <button type="button" class="btn btn-secondary" @click="open = !open"><x-icon name="download" class="size-4"/> {{ $variantLabel }}</button>
                    <div x-cloak x-show="open" @click.outside="open = false" class="panel absolute right-0 z-10 mt-1 w-40 p-1 text-sm">
                        @can('reports.export_excel')
                            <a href="{{ $exportUrl('xlsx', $variant) }}" class="block rounded px-3 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800">Excel (.xlsx)</a>
                            <a href="{{ $exportUrl('csv', $variant) }}" class="block rounded px-3 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800">CSV</a>
                        @endcan
                        @can('reports.export_pdf')
                            <a href="{{ $exportUrl('pdf', $variant) }}" class="block rounded px-3 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800">PDF</a>
                        @endcan
                    </div>
                </div>
            @endforeach
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="from" type="date" label="Desde" :value="$filters->from->toDateString()"/>
        <x-input name="to" type="date" label="Hasta" :value="$filters->to->toDateString()"/>
        @if ($packer)<input type="hidden" name="packer_id" value="{{ $packer->id }}">@endif
        @foreach ($visibleFilters as $key)
            @if (isset($options[$key]))
                <x-select :name="$key" :label="$labels[$key] ?? $key" :options="$options[$key]" :value="$key === 'group' ? $filters->group : ($key === 'status' ? $filters->status : $filters->get($key))"
                          :placeholder="$key === 'group' ? null : 'Todos'"/>
            @elseif ($key === 'lot')
                <x-input name="lot" label="Lote" :value="$filters->lotCode" class="code"/>
            @elseif ($key === 'load')
                <x-input name="load" label="Carga" :value="$filters->loadNumber" class="code"/>
            @endif
        @endforeach
    </x-filters>

    @if ($packerTotals)
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
            <x-stat label="Cajones procesados" :value="num($packerTotals['crates'])" icon="box"/>
            <x-stat label="Kg procesados" :value="kg($packerTotals['kg'], 1)" icon="scale" color="accent"/>
            <x-stat label="Kg promedio por cajón" :value="$packerTotals['crates'] ? num($packerTotals['kg'] / $packerTotals['crates'], 2) : '—'" icon="chart-bar" color="sky"/>
        </div>
    @endif

    @if ($chart && count($chart['rows']))
        <x-panel class="mb-6">
            <div class="h-72"><canvas id="report-chart" aria-label="Gráfico del reporte"></canvas></div>
        </x-panel>
    @endif

    <x-table>
        <thead><tr>@foreach ($dataset->columns as $column)<th @class(['num' => in_array($column['type'], Format::NUMERIC, true)])>{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($dataset->columns as $key => $column)
                        <td @class(['num' => in_array($column['type'], Format::NUMERIC, true)])>{{ Format::display($row[$key] ?? null, $column['type']) }}</td>
                    @endforeach
                </tr>
            @empty
                <x-empty :colspan="count($dataset->columns)" message="Sin datos para los filtros elegidos."/>
            @endforelse
            @if ($dataset->totals && count($rows))
                <tr class="font-semibold">
                    @foreach ($dataset->columns as $key => $column)
                        <td @class(['num' => in_array($column['type'], Format::NUMERIC, true)])>{{ array_key_exists($key, $dataset->totals) ? Format::display($dataset->totals[$key], $column['type']) : '' }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
        @if (count($rows) >= 500)
            <x-slot:footer><p class="text-xs text-stone-500">Se muestran las primeras 500 filas. Exportá el reporte para verlo completo.</p></x-slot:footer>
        @endif
    </x-table>

    @if ($chart && count($chart['rows']))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const c = {{ \Illuminate\Support\Js::from($chart) }};
                    const el = document.getElementById('report-chart');
                    if (!el || !window.Chart) return;
                    new Chart(el, {
                        type: c.type,
                        data: { labels: c.rows.map(r => r.label), datasets: [{ label: c.label, data: c.rows.map(r => r.value), backgroundColor: c.type === 'bar' ? window.chartColors[0] : window.chartColors, borderWidth: 0, borderRadius: c.type === 'bar' ? 4 : 0 }] },
                        options: { plugins: { legend: { display: c.type !== 'bar', position: 'right' } } },
                    });
                });
            </script>
        @endpush
    @endif
</x-layouts.app>
