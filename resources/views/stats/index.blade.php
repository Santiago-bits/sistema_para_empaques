@php use App\Services\Reports\Format; @endphp
<x-layouts.app title="Estadísticas">
    <x-page-header title="Estadísticas" :subtitle="$filters->from->format('d/m/Y').' al '.$filters->to->format('d/m/Y')" :back="route('reports.index')"/>

    <form method="GET" class="panel mb-6 flex flex-wrap items-end gap-3 p-4" data-allow-resubmit x-data="{ period: '{{ $period }}' }">
        <div class="flex flex-wrap gap-1">
            @foreach ($periods as $key => $label)
                <label class="cursor-pointer rounded-lg border border-stone-300 px-3 py-2 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-600 has-[:checked]:text-white dark:border-stone-700">
                    <input type="radio" name="period" value="{{ $key }}" class="sr-only" x-model="period" @checked($period === $key) @change="if (period !== 'range') $el.form.submit()"> {{ $label }}
                </label>
            @endforeach
        </div>
        <template x-if="period === 'range'">
            <div class="flex items-end gap-2">
                <div><label class="form-label">Desde</label><input type="date" name="from" class="form-input" value="{{ $filters->from->toDateString() }}"></div>
                <div><label class="form-label">Hasta</label><input type="date" name="to" class="form-input" value="{{ $filters->to->toDateString() }}"></div>
                <button class="btn btn-primary">Ver</button>
            </div>
        </template>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <x-stat label="Kg procesados" :value="kg($indicators['kg_processed'], 0)" icon="scale"/>
        <x-stat label="Cajones" :value="num($indicators['crates_processed'])" icon="box" color="sky"/>
        <x-stat label="Kg por hora" :value="num($indicators['kg_per_hour'], 1)" icon="clock" color="violet"/>
        <x-stat label="Cajones por hora" :value="num($indicators['crates_per_hour'], 1)" icon="clock" color="violet"/>
        <x-stat label="Kg por embalador" :value="num($indicators['kg_per_packer'], 1)" icon="users" color="accent"/>
        <x-stat label="Kg prom. / cajón" :value="num($indicators['avg_kg_per_crate'], 2)" icon="chart-bar" color="stone"/>
        <x-stat label="% merma" :value="pct($indicators['waste_pct'])" icon="trash" color="red"/>
        <x-stat label="Tiempo prom. de carga" :value="$indicators['avg_load_minutes'] !== null ? Format::minutes($indicators['avg_load_minutes']) : '—'" icon="truck" color="amber"/>
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-2">
        @foreach (['production' => 'Producción (kg)', 'packers' => 'Producción por embalador (kg)', 'varieties' => 'Por variedad (kg)', 'sizes' => 'Por tamaño (cajones)', 'waste' => 'Merma por motivo (kg)', 'loads' => 'Despachos (kg)'] as $key => $title)
            <x-panel :title="$title">
                @if (count($charts[$key]))
                    <div class="h-64"><canvas id="chart-{{ $key }}" aria-label="{{ $title }}"></canvas></div>
                @else
                    <p class="py-20 text-center text-sm text-stone-500">Sin datos en el período.</p>
                @endif
            </x-panel>
        @endforeach
    </div>

    <x-panel title="Comparaciones históricas" :padding="false">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Indicador</th>@foreach ($comparisons as $c)<th class="num" colspan="2">{{ $c['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($metrics as $metric => [$label, $type])
                        <tr>
                            <td>{{ $label }}</td>
                            @foreach ($comparisons as $c)
                                @php $m = $c['metrics'][$metric] ?? null; @endphp
                                <td class="num">{{ $m ? Format::display($m['current'], $type) : '—' }}<br><span class="text-xs text-stone-500">antes {{ $m ? Format::display($m['previous'], $type) : '—' }}</span></td>
                                <td class="num w-20">
                                    @if ($m && $m['pct'] !== null)
                                        <span @class(['text-xs font-semibold', 'text-emerald-600' => $m['pct'] >= 0 xor in_array($metric, ['rejected_kg', 'waste_pct']), 'text-red-600' => $m['pct'] < 0 xor in_array($metric, ['rejected_kg', 'waste_pct'])])>{{ $m['pct'] >= 0 ? '▲' : '▼' }} {{ num(abs($m['pct']), 1) }} %</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const c = {{ \Illuminate\Support\Js::from($charts) }}, colors = window.chartColors;
                const draw = (key, type, labels, data, opts = {}) => {
                    const el = document.getElementById('chart-' + key);
                    if (!el || !window.Chart) return;
                    new Chart(el, { type, data: { labels, datasets: [{ label: 'Kg', data, backgroundColor: type === 'bar' ? colors[0] : colors, borderColor: colors[1], fill: type === 'line', tension: .3, borderWidth: type === 'line' ? 2 : 0, borderRadius: 4 }] },
                        options: Object.assign({ plugins: { legend: { display: type === 'doughnut', position: 'right' } } }, opts) });
                };
                draw('production', 'line', c.production.map(r => r.label), c.production.map(r => r.kg));
                draw('packers', 'bar', c.packers.map(r => r.label), c.packers.map(r => r.kg), { indexAxis: 'y' });
                draw('varieties', 'doughnut', c.varieties.map(r => r.label), c.varieties.map(r => r.kg));
                draw('sizes', 'bar', c.sizes.map(r => r.label), c.sizes.map(r => r.crates));
                draw('waste', 'doughnut', c.waste.map(r => r.label), c.waste.map(r => r.kg));
                draw('loads', 'bar', c.loads.map(r => r.label), c.loads.map(r => r.kg));
            });
        </script>
    @endpush
</x-layouts.app>
