@php
    $periods = \App\Http\Controllers\Admin\ActivityController::PERIODS;
    $chart = ['labels' => $daily->map(fn ($d) => \Illuminate\Support\Carbon::parse($d->day)->format('d/m'))->values(), 'users' => $daily->pluck('users')->map(fn ($v) => (int) $v)->values(), 'actions' => $daily->pluck('actions')->map(fn ($v) => (int) $v)->values()];
    $maxArea = max($areas ?: [1]);
@endphp
<x-layouts.app title="Actividad del personal">
    <x-page-header title="Actividad del personal" subtitle="Quién usa el sistema, cuánto y en qué partes.">
        <x-slot:actions>
            @foreach ($periods as $value => $label)
                <a href="{{ route('admin.activity.index', ['days' => $value]) }}" @class(['btn btn-sm', 'btn-primary' => $days === $value, 'btn-secondary' => $days !== $value])>{{ $label }}</a>
            @endforeach
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-stat label="Conectados ahora" :value="num($totals['online'])" icon="signal" color="brand"/>
        <x-stat label="Usaron el sistema" :value="num($totals['active']).' de '.num($totals['total'])" icon="users" color="violet"/>
        <x-stat label="Ingresos (logins)" :value="num($totals['logins'])" icon="logout" color="sky"/>
        <x-stat label="Operaciones registradas" :value="num($totals['actions'])" icon="list" color="accent"/>
        <x-stat label="Cajones registrados" :value="num($totals['crates'])" icon="box" color="stone"/>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <x-panel title="Uso por día" class="lg:col-span-2">
            @if ($daily->isEmpty())
                <p class="text-sm text-stone-500">Sin actividad en el período.</p>
            @else
                <div class="h-56" x-data x-init="new Chart($refs.c, { type: 'bar', data: { labels: {{ \Illuminate\Support\Js::from($chart['labels']) }}, datasets: [
                    { label: 'Operaciones', data: {{ \Illuminate\Support\Js::from($chart['actions']) }}, backgroundColor: window.chartColors[0], borderRadius: 4 },
                    { label: 'Personas', data: {{ \Illuminate\Support\Js::from($chart['users']) }}, type: 'line', borderColor: window.chartColors[1], yAxisID: 'y1', tension: .3 } ] },
                    options: { scales: { y: { beginAtZero: true }, y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 } } } } })">
                    <canvas x-ref="c"></canvas>
                </div>
            @endif
        </x-panel>
        <x-panel title="Partes más usadas">
            @forelse ($areas as $area => $count)
                <div class="mb-2">
                    <div class="flex justify-between text-sm"><span>{{ $area }}</span><span class="tabular-nums text-stone-500">{{ num($count) }}</span></div>
                    <div class="mt-1 h-1.5 rounded-full bg-stone-100 dark:bg-stone-800"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ round($count / $maxArea * 100) }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-stone-500">Sin datos.</p>
            @endforelse
        </x-panel>
    </div>

    <x-table>
        <thead><tr><th>Empleado</th><th>Acceso</th><th>Último ingreso</th><th class="num">Días activos</th><th class="num">Ingresos</th><th class="num">Operaciones</th><th class="num">Cajones</th></tr></thead>
        <tbody>
            @forelse ($users as $row)
                <tr>
                    <td>
                        @if ($row->online)<span class="mr-1 text-emerald-600" title="Conectado ahora">●</span>@endif
                        @can('users.view')<a href="{{ route('admin.users.show', $row->user) }}" class="font-medium text-stone-900 hover:underline dark:text-white">{{ $row->user->full_name }}</a>@endcan
                        <span class="block text-xs text-stone-500">{{ $row->user->username }}</span>
                    </td>
                    <td class="text-xs text-stone-600 dark:text-stone-300">
                        {{ $row->user->role?->slug === \App\Support\Sectors::ROLE
                            ? collect(\App\Support\Sectors::of($row->user))->map(fn ($k) => \App\Support\Sectors::all()[$k]['label'])->join(', ')
                            : $row->user->role?->name }}
                    </td>
                    <td class="whitespace-nowrap text-stone-500">{{ $row->user->last_login_at ? $row->user->last_login_at->diffForHumans() : 'Nunca' }}</td>
                    <td class="num">{{ num($row->days) }}</td>
                    <td class="num">{{ num($row->logins) }}</td>
                    <td class="num">{{ num($row->actions) }}</td>
                    <td class="num">{{ num($row->crates) }}</td>
                </tr>
            @empty
                <x-empty colspan="7" message="No hay usuarios activos."/>
            @endforelse
        </tbody>
    </x-table>
    <p class="form-hint mt-2">Operaciones = altas, cambios, anulaciones y demás acciones registradas en la auditoría. Cajones = registros de producción (romaneo) hechos por esa persona.</p>
</x-layouts.app>
