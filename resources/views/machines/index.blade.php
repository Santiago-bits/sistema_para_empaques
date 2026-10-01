<x-layouts.app title="Mantenimiento">
    <x-page-header title="Maquinaria y mantenimiento" subtitle="Preventivos, correctivos y emergencias de cada máquina.">
        <x-slot:actions>
            @can('maintenance.manage')
                <a href="{{ route('machines.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nueva máquina</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat label="Máquinas" :value="num($summary['machines'])" icon="wrench"/>
        <x-stat label="Operativas" :value="num($summary['operational'])" icon="check" color="sky"/>
        <x-stat label="En mantenimiento" :value="num($summary['in_maintenance'])" icon="clock" color="amber"/>
        <x-stat label="Vencidas" :value="num($summary['overdue'])" icon="alert" color="red" :href="route('machines.index', ['due' => 'overdue'])"/>
        <x-stat :label="'Próximas ('.$days.' días)'" :value="num($summary['upcoming'])" icon="clock" color="violet" :href="route('machines.index', ['due' => 'upcoming'])"/>
    </div>

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-select name="due" label="Próximo mantenimiento" :options="['overdue' => 'Vencido', 'upcoming' => 'Próximo']" :value="request('due')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Código</th><th>Máquina</th><th>Marca / modelo</th><th>Ubicación</th><th>Estado</th><th>Último</th><th>Próximo</th><th></th></tr></thead>
        <tbody>
            @forelse ($machines as $machine)
                @php $due = \App\Services\MaintenanceService::dueStatus($machine, $days); @endphp
                <tr>
                    <td class="code">{{ $machine->code }}</td>
                    <td class="font-medium text-stone-900 dark:text-white">{{ $machine->name }}</td>
                    <td>{{ trim($machine->brand.' '.$machine->model) ?: '—' }}</td>
                    <td>{{ $machine->location?->name ?? '—' }}</td>
                    <td><x-badge :color="['operational' => 'emerald', 'maintenance' => 'amber', 'out_of_service' => 'red'][$machine->status] ?? 'stone'">{{ $statuses[$machine->status] ?? $machine->status }}</x-badge></td>
                    <td class="tabular-nums">{{ $machine->maintenances_max_date ? fdate(\Illuminate\Support\Carbon::parse($machine->maintenances_max_date)) : '—' }}</td>
                    <td class="tabular-nums">
                        {{ fdate($machine->next_maintenance_on) }}
                        @if ($due === 'overdue')<x-badge color="red">Vencido</x-badge>@elseif ($due === 'upcoming')<x-badge color="amber">Próximo</x-badge>@endif
                    </td>
                    <td class="text-right"><a href="{{ route('machines.show', $machine) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="8"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $machines->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
