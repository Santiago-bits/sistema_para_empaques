<x-layouts.app title="Controles de calidad">
    <x-page-header title="Controles de calidad" subtitle="Controles sobre cajones, lotes y pallets">
        <x-slot:actions>
            <a href="{{ route('rejects.index') }}" class="btn btn-secondary"><x-icon name="trash" class="size-4"/> Rechazos y merma</a>
            @can('quality.manage')
                <a href="{{ route('quality.create') }}" class="btn btn-primary"><x-icon name="scan" class="size-4"/> Control rápido</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <x-stat label="Controles hoy" :value="num($today->sum())" icon="check-badge" color="stone"/>
        <x-stat label="Aprobados hoy" :value="num($today['approved'] ?? 0)" icon="check" color="brand"/>
        <x-stat label="Rechazados hoy" :value="num($today['rejected'] ?? 0)" icon="ban" color="red"/>
        <x-stat label="Observados hoy" :value="num($today['observed'] ?? 0)" icon="eye" color="amber"/>
    </div>

    <x-filters>
        <x-input name="code" label="Código" :value="request('code')" placeholder="Cajón, pallet o lote"/>
        <x-select name="target" label="Objeto" :options="$targets" :value="request('target')" placeholder="Todos"/>
        <x-select name="result" label="Resultado" :options="$results" :value="request('result')" placeholder="Todos"/>
        <x-select name="user_id" label="Responsable" :options="$users" :value="request('user_id')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead>
            <tr>
                <th>Fecha</th><th>Objeto</th><th>Resultado</th><th>Calidad</th><th>Calibre</th>
                <th class="num">% daños</th><th class="num">% golpes</th><th class="num">% podr.</th><th class="num">% rechazo</th>
                <th>Responsable</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($controls as $control)
                <tr>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($control->controlled_at, true) }}</td>
                    <td class="whitespace-nowrap">
                        @if ($control->crate)
                            <span class="text-xs text-stone-500">Cajón</span> <span class="code">{{ $control->crate->code }}</span>
                        @elseif ($control->pallet)
                            <span class="text-xs text-stone-500">Pallet</span> <span class="code">{{ $control->pallet->code }}</span>
                        @elseif ($control->lot)
                            <span class="text-xs text-stone-500">Lote</span> <span class="code">{{ $control->lot->code }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <x-badge :color="match ($control->result) { 'approved' => 'emerald', 'rejected' => 'red', default => 'amber' }">{{ $results[$control->result] ?? $control->result }}</x-badge>
                        @if ($control->rejects_count)
                            <x-badge color="orange">{{ $control->rejects_count }} rechazo(s)</x-badge>
                        @endif
                    </td>
                    <td>{{ $control->grade ?? '—' }}</td>
                    <td>{{ $control->caliber ?? '—' }}</td>
                    <td class="num">{{ num($control->damage_pct, 1) }}</td>
                    <td class="num">{{ num($control->bruise_pct, 1) }}</td>
                    <td class="num">{{ num($control->rot_pct, 1) }}</td>
                    <td class="num">{{ num($control->reject_pct, 1) }}</td>
                    <td>{{ $control->user?->full_name ?? '—' }}</td>
                    <td class="text-right"><a href="{{ route('quality.show', $control) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="11" message="Todavía no hay controles de calidad registrados."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $controls->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
