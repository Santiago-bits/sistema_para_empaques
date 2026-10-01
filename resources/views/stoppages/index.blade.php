<x-layouts.app title="Paradas de producción">
    <x-page-header title="Paradas de producción" subtitle="Interrupciones de las líneas: motivo, duración y responsable."/>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <x-panel title="Iniciar parada">
            <form method="POST" action="{{ route('stoppages.store') }}" class="space-y-4">
                @csrf
                <x-select name="reason_id" label="Motivo" :options="$reasons" placeholder="Seleccionar…" required/>
                <x-select name="production_line_id" label="Línea" :options="$lines" placeholder="General"/>
                <x-input name="started_at" type="datetime-local" label="Inicio" hint="Vacío = ahora."/>
                <x-textarea name="notes" label="Observaciones" rows="2"/>
                <button class="btn btn-warning btn-lg w-full"><x-icon name="pause" class="size-5"/> Iniciar parada</button>
            </form>
        </x-panel>

        <div class="space-y-3 lg:col-span-2">
            <h2 class="text-sm font-semibold text-stone-600 dark:text-stone-300">Paradas en curso</h2>
            @forelse ($open as $stoppage)
                <div class="panel flex flex-wrap items-center justify-between gap-4 border-amber-400 p-4">
                    <div>
                        <p class="text-lg font-semibold text-amber-700 dark:text-amber-400">{{ $stoppage->reason?->name }}</p>
                        <p class="text-sm text-stone-500">
                            {{ $stoppage->productionLine?->name ?? 'General' }} · desde {{ fdate($stoppage->started_at, true) }}
                            ({{ $stoppage->started_at->diffForHumans(null, true) }}) · {{ $stoppage->user?->full_name }}
                        </p>
                        @if ($stoppage->notes)<p class="text-sm text-stone-600 dark:text-stone-400">{{ $stoppage->notes }}</p>@endif
                    </div>
                    <form method="POST" action="{{ route('stoppages.update', $stoppage) }}" x-data x-confirm="¿Finalizar la parada?">
                        @csrf @method('PUT')
                        <button class="btn btn-primary btn-lg"><x-icon name="check" class="size-5"/> Finalizar parada</button>
                    </form>
                </div>
            @empty
                <div class="panel p-6 text-center text-sm text-stone-500">No hay paradas en curso. Las líneas están operando.</div>
            @endforelse
        </div>
    </div>

    <x-filters>
        <x-input name="date_from" type="date" label="Desde" :value="request('date_from')"/>
        <x-input name="date_to" type="date" label="Hasta" :value="request('date_to')"/>
        <x-select name="reason_id" label="Motivo" :options="$allReasons" :value="request('reason_id')" placeholder="Todos"/>
        <x-select name="production_line_id" label="Línea" :options="$lines" :value="request('production_line_id')" placeholder="Todas"/>
        <x-select name="shift_id" label="Turno" :options="$shifts" :value="request('shift_id')" placeholder="Todos"/>
    </x-filters>

    <p class="mb-3 text-sm text-stone-600 dark:text-stone-400">Tiempo total detenido en el filtro: <strong class="tabular-nums">{{ intdiv($totalMinutes, 60) }} h {{ $totalMinutes % 60 }} min</strong></p>

    <x-table>
        <thead><tr><th>Inicio</th><th>Fin</th><th class="num">Duración</th><th>Motivo</th><th>Línea</th><th>Turno</th><th>Responsable</th><th>Observaciones</th></tr></thead>
        <tbody>
            @forelse ($stoppages as $stoppage)
                <tr>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($stoppage->started_at, true) }}</td>
                    <td class="tabular-nums whitespace-nowrap">{{ $stoppage->ended_at ? fdate($stoppage->ended_at, true) : '' }} @unless ($stoppage->ended_at)<x-badge color="amber">En curso</x-badge>@endunless</td>
                    <td class="num">{{ $stoppage->duration_minutes !== null ? $stoppage->duration_minutes.' min' : '—' }}</td>
                    <td>{{ $stoppage->reason?->name }}</td>
                    <td>{{ $stoppage->productionLine?->name ?? 'General' }}</td>
                    <td>{{ $stoppage->shift?->name }}</td>
                    <td>{{ $stoppage->user?->full_name }}</td>
                    <td class="text-stone-500">{{ $stoppage->notes }}</td>
                </tr>
            @empty
                <x-empty colspan="8"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $stoppages->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
