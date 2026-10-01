<x-layouts.app :title="$room->name">
    <x-page-header :title="$room->name" :subtitle="'Rango '.num($room->temp_min, 1).' a '.num($room->temp_max, 1).' °C'.($room->location ? ' · '.$room->location->path() : '')" :back="route('cold-rooms.index')">
        <x-slot:actions>
            @can('cold_rooms.manage')
                <a href="{{ route('cold-rooms.edit', $room) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($alert)
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
            <strong>{{ $alert->title }}.</strong> {{ $alert->message }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-panel title="Última lectura">
                @if ($latest)
                    <p @class(['text-5xl font-bold tabular-nums', 'text-red-600' => $latest->out_of_range, 'text-sky-700 dark:text-sky-300' => ! $latest->out_of_range])>{{ num($latest->temperature, 1) }} °C</p>
                    <p class="mt-1 text-sm text-stone-500">
                        @if ($latest->humidity !== null) Humedad {{ num($latest->humidity, 0) }} % · @endif {{ fdate($latest->recorded_at, true) }}
                        · {{ $latest->source === 'sensor' ? 'Sensor' : 'Manual' }}
                    </p>
                @else
                    <p class="text-sm text-stone-500">Sin lecturas.</p>
                @endif
            </x-panel>
            @can('cold_rooms.manage')
                <x-panel title="Cargar lectura manual">
                    <form method="POST" action="{{ route('cold-rooms.readings.store', $room) }}" class="space-y-3">
                        @csrf
                        <x-input name="temperature" label="Temperatura (°C)" inputmode="decimal" required autofocus/>
                        <x-input name="humidity" label="Humedad (%)" inputmode="decimal"/>
                        <x-input name="recorded_at" type="datetime-local" label="Fecha y hora" hint="Vacío = ahora."/>
                        <button class="btn btn-primary w-full">Registrar lectura</button>
                    </form>
                </x-panel>
            @endcan
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Últimas 48 horas">
                <div class="h-72"><canvas id="temp-chart" aria-label="Temperatura de las últimas 48 horas"></canvas></div>
            </x-panel>
            <x-panel title="Lecturas" :padding="false">
                <table class="table">
                    <thead><tr><th>Fecha y hora</th><th class="num">Temp. °C</th><th class="num">Humedad %</th><th>Origen</th><th>Usuario</th></tr></thead>
                    <tbody>
                        @forelse ($readings as $r)
                            <tr @class(['bg-red-50 dark:bg-red-950/30' => $r->out_of_range])>
                                <td class="tabular-nums">{{ fdate($r->recorded_at, true) }}</td>
                                <td class="num">{{ num($r->temperature, 1) }} @if ($r->out_of_range)<x-badge color="red">Fuera</x-badge>@endif</td>
                                <td class="num">{{ $r->humidity !== null ? num($r->humidity, 0) : '—' }}</td>
                                <td>{{ $r->source === 'sensor' ? 'Sensor' : 'Manual' }}</td>
                                <td>{{ $r->user?->full_name ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-empty colspan="5"/>
                        @endforelse
                    </tbody>
                </table>
                @if ($readings->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $readings->links() }}</div>@endif
            </x-panel>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const d = {{ \Illuminate\Support\Js::from($chart) }};
                const el = document.getElementById('temp-chart');
                if (!el || !window.Chart) return;
                new Chart(el, {
                    type: 'line',
                    data: {
                        labels: d.labels,
                        datasets: [
                            { label: 'Temperatura °C', data: d.temperature, borderColor: '#0ea5e9', backgroundColor: 'rgba(14,165,233,.15)', fill: true, tension: .3, pointRadius: 0 },
                            { label: 'Máximo', data: d.labels.map(() => d.max), borderColor: '#ef4444', borderDash: [6, 4], pointRadius: 0, borderWidth: 1 },
                            { label: 'Mínimo', data: d.labels.map(() => d.min), borderColor: '#f97316', borderDash: [6, 4], pointRadius: 0, borderWidth: 1 },
                        ],
                    },
                    options: { interaction: { mode: 'index', intersect: false }, scales: { x: { ticks: { maxTicksLimit: 8 } } } },
                });
            });
        </script>
    @endpush
</x-layouts.app>
