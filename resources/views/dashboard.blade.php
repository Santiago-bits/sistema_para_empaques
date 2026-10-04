@php
    $t = $stats['today'];
    $cmp = $stats['comparisons'];
    $delta = fn ($key, $metric = 'kg') => $cmp[$key]['metrics'][$metric]['pct'] ?? null;
@endphp
<x-layouts.app title="Inicio">
    <x-page-header title="Inicio" :subtitle="'Hoy, '.now()->translatedFormat('l j \d\e F').' · actualizado '.$stats['generated_at']">
        <x-slot:actions>
            @can('production.scan')
                <a href="{{ route('production.scan') }}" class="btn btn-primary"><x-icon name="scan" class="size-4"/> Escanear cajones</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div x-data="dashboard({{ \Illuminate\Support\Js::from(['dataUrl' => route('dashboard.data'), 'charts' => $stats['charts']]) }})" class="space-y-6">
        {{-- Objetivo diario --}}
        @if ($stats['target']['kg'] > 0)
            <div class="panel p-5">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold tracking-wider text-stone-500 uppercase">Producción del día</p>
                        <p class="text-3xl font-bold tabular-nums">{{ kg($stats['target']['actual'], 0) }}
                            <span class="text-base font-medium text-stone-500">de {{ kg($stats['target']['kg'], 0) }}</span></p>
                    </div>
                    <p @class(['text-4xl font-bold tabular-nums', 'text-brand-600' => ($stats['target']['pct'] ?? 0) >= 100, 'text-accent-500' => ($stats['target']['pct'] ?? 0) < 100])>
                        {{ num($stats['target']['pct'] ?? 0, 0) }} %</p>
                </div>
                <x-progress class="mt-3" :value="$stats['target']['actual']" :max="$stats['target']['kg']"/>
            </div>
        @endif

        {{-- Indicadores --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <x-stat label="Cajones ingresados hoy" :value="num($t['crates_in'])" icon="box" color="stone"/>
            <x-stat label="Procesados hoy" :value="num($t['crates_processed'])" icon="check-badge" :delta="$delta('day', 'crates')" hint="vs ayer"/>
            <x-stat label="Cajones pendientes" :value="num($stats['pending_crates'])" icon="clock" :color="$stats['pending_crates'] ? 'amber' : 'stone'" :href="route('crates.index', ['status' => 'registered'])"/>
            <x-stat label="Kg hoy" :value="kg($t['kg_processed'], 0)" icon="scale" color="accent" :delta="$delta('day')" hint="vs ayer"/>
            <x-stat label="Kg semana" :value="kg($stats['week_kg'], 0)" icon="chart-bar" color="sky" :delta="$delta('week')" hint="vs sem. ant."/>
            <x-stat label="Kg mes" :value="kg($stats['month_kg'], 0)" icon="chart-line" color="violet" :delta="$delta('month')" hint="vs mes ant."/>
            @module('pallets')
                <x-stat label="Pallets disponibles" :value="num($stats['pallets_available'])" icon="pallet" :href="route('pallets.index', ['status' => 'with_product'])"/>
                <x-stat label="Pallets preparados" :value="num($stats['pallets_prepared'])" icon="pallet" color="violet"/>
            @endmodule
            @module('loads')
                <x-stat label="Cargas pendientes" :value="num($stats['pending_loads']['total'])" icon="truck" color="amber"
                        :hint="$stats['pending_loads']['draft'].' en armado · '.$stats['pending_loads']['closed'].' cerradas'" :href="route('loads.index')"/>
                <x-stat label="Camiones despachados hoy" :value="num($t['trucks_dispatched'])" icon="truck" color="sky" :hint="kg($t['kg_dispatched'], 0)"/>
            @endmodule
            @module('quality')
                <x-stat label="Rechazos hoy" :value="num($t['rejects'])" icon="trash" color="red" :hint="kg($t['kg_rejected'], 0)" :href="route('rejects.index')"/>
                <x-stat label="Merma hoy" :value="pct($t['waste_pct'])" icon="alert" :color="$t['waste_pct'] > 5 ? 'red' : 'stone'"/>
            @endmodule
        </div>

        {{-- Gráficos --}}
        <div class="grid gap-6 xl:grid-cols-3">
            <x-panel title="Producción por hora (hoy)" class="xl:col-span-2">
                <div class="h-64"><canvas x-ref="hourly" aria-label="Producción por hora"></canvas></div>
            </x-panel>
            <x-panel title="Por variedad (hoy)">
                <div class="h-64"><canvas x-ref="varieties" aria-label="Producción por variedad"></canvas></div>
                <p x-show="!charts.varieties.length" class="text-center text-sm text-stone-500">Sin producción hoy.</p>
            </x-panel>
            <x-panel title="Últimos 14 días (kg)" class="xl:col-span-2">
                <div class="h-64"><canvas x-ref="daily" aria-label="Producción diaria"></canvas></div>
            </x-panel>
            <x-panel title="Por tamaño (hoy)">
                <div class="h-64"><canvas x-ref="sizes" aria-label="Producción por tamaño"></canvas></div>
            </x-panel>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-panel title="Embaladores del día" :padding="false">
                <table class="table">
                    <thead><tr><th>Embalador</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                    <tbody>
                        @forelse ($stats['charts']['packers'] as $p)
                            <tr><td>{{ $p['label'] }}</td><td class="num">{{ num($p['crates']) }}</td><td class="num">{{ num($p['kg'], 1) }}</td></tr>
                        @empty
                            <x-empty colspan="3" message="Sin producción hoy."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>

            <x-panel title="Ahora mismo · últimos escaneos" :padding="false">
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    <template x-for="r in live" :key="r.crate + r.time">
                        <li class="flex items-center justify-between gap-3 px-4 py-2">
                            <div class="min-w-0"><p class="code" x-text="r.crate"></p><p class="truncate text-xs text-stone-500" x-text="[r.packer, r.variety, r.size].filter(Boolean).join(' · ')"></p></div>
                            <div class="text-right"><p class="tabular-nums" x-text="fmt(r.weight) + ' kg'"></p><p class="text-xs text-stone-500" x-text="r.time"></p></div>
                        </li>
                    </template>
                    <li x-show="!live.length" class="px-4 py-6 text-center text-stone-500">Sin escaneos recientes.</li>
                </ul>
            </x-panel>

            <div class="min-w-0 space-y-6">
                @can('alerts.view')
                    <x-panel title="Alertas abiertas" :padding="false">
                        <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                            @forelse ($alerts as $alert)
                                <li class="flex items-start gap-2 px-4 py-2">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $alert->severity === 'critical' ? 'bg-red-500' : ($alert->severity === 'warning' ? 'bg-amber-500' : 'bg-sky-500') }}"></span>
                                    <div class="min-w-0"><p class="font-medium [overflow-wrap:anywhere]">{{ $alert->title }}</p><p class="text-xs text-stone-500">{{ $alert->updated_at->diffForHumans() }}</p></div>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-center text-stone-500">Sin alertas abiertas. 👍</li>
                            @endforelse
                        </ul>
                    </x-panel>
                @endcan
                @if ($capacity)
                    <x-panel title="Capacidad del galpón">
                        <x-progress :value="$capacity['pallets']" :max="max(1, $capacity['capacity'])" :label="num($capacity['pallets']).' de '.num($capacity['capacity']).' pallets'"/>
                        <p class="mt-2 text-xs text-stone-500">{{ num($capacity['free']) }} espacios libres @if ($capacity['unlocated']) · {{ num($capacity['unlocated']) }} pallets sin ubicar @endif</p>
                    </x-panel>
                @endif
                @if ($recentLoads->isNotEmpty())
                    <x-panel title="Cargas recientes" :padding="false">
                        <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                            @foreach ($recentLoads as $load)
                                <li class="flex items-center justify-between px-4 py-2">
                                    <a href="{{ route('loads.show', $load) }}" class="code link">{{ $load->number }}</a>
                                    <span class="min-w-0 flex-1 truncate px-2 text-xs text-stone-500">{{ $load->destination?->name }}</span>
                                    <x-status :status="$load->status"/>
                                </li>
                            @endforeach
                        </ul>
                    </x-panel>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.dashboard = function (config) {
                // Fuera del estado de Alpine: si los gráficos quedan dentro de un Proxy reactivo, Chart.js se rompe al redibujar.
                const instances = {};

                return {
                    charts: config.charts,
                    live: {{ \Illuminate\Support\Js::from($live->map(fn ($r) => ['crate' => $r->crate?->code, 'packer' => $r->packer?->full_name, 'variety' => $r->variety?->name, 'size' => $r->size?->name, 'weight' => (float) $r->weight, 'time' => $r->recorded_at->format('H:i')])) }},
                    fmt(n, d = 2) { return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: d, maximumFractionDigits: d }); },
                    init() {
                        this.draw();
                        window.addEventListener('theme-changed', () => this.draw());
                        setInterval(() => { if (!document.hidden) this.refresh(); }, 60000);
                    },
                    async refresh() {
                        try {
                            const data = await window.api(config.dataUrl);
                            this.live = data.live;
                            this.charts = data.stats.charts;
                            this.draw();
                        } catch (e) {}
                    },
                    chart(ref, type, labels, datasets, options = {}) {
                        if (instances[ref]) instances[ref].destroy();
                        const el = this.$refs[ref];
                        if (!el || !window.Chart) return;
                        instances[ref] = new Chart(el, { type, data: { labels, datasets }, options });
                    },
                    draw() {
                        const c = this.charts, colors = window.chartColors;
                        const hours = c.hourly.filter(h => h.hour >= 5 && h.hour <= 23);
                        this.chart('hourly', 'bar', hours.map(h => h.hour + ' h'), [{ label: 'Kg', data: hours.map(h => h.kg), backgroundColor: colors[0], borderRadius: 4 }], { plugins: { legend: { display: false } } });
                        this.chart('daily', 'line', c.daily.map(d => d.label), [{ label: 'Kg', data: c.daily.map(d => d.kg), borderColor: colors[1], backgroundColor: 'rgba(249,115,22,.12)', fill: true, tension: .3 }], { plugins: { legend: { display: false } } });
                        this.chart('varieties', 'doughnut', c.varieties.map(v => v.label), [{ data: c.varieties.map(v => v.kg), backgroundColor: c.varieties.map((v, i) => v.color || colors[i % colors.length]), borderWidth: 0 }], { plugins: { legend: { position: 'right' } } });
                        this.chart('sizes', 'bar', c.sizes.map(s => s.label), [{ label: 'Cajones', data: c.sizes.map(s => s.crates), backgroundColor: colors[2], borderRadius: 4 }], { plugins: { legend: { display: false } } });
                    },
                };
            };
        </script>
    @endpush
</x-layouts.app>
