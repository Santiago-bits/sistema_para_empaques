<x-layouts.app :title="$machine->name">
    <x-page-header :title="$machine->name" :subtitle="$machine->code.' · '.trim($machine->brand.' '.$machine->model)" :back="route('machines.index')">
        <x-slot:actions>
            <x-badge :color="['operational' => 'emerald', 'maintenance' => 'amber', 'out_of_service' => 'red'][$machine->status] ?? 'stone'" class="text-sm">{{ $statuses[$machine->status] ?? $machine->status }}</x-badge>
            @if ($due === 'overdue')<x-badge color="red" class="text-sm">Mantenimiento vencido</x-badge>@elseif ($due === 'upcoming')<x-badge color="amber" class="text-sm">Mantenimiento próximo</x-badge>@endif
            @can('maintenance.manage')
                <a href="{{ route('machines.edit', $machine) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-panel title="Datos">
                <x-dl class="!grid-cols-1" :items="[
                    'Número de serie' => $machine->serial_number,
                    'Ubicación' => $machine->location?->path(),
                    'Próximo mantenimiento' => fdate($machine->next_maintenance_on),
                ]"/>
            </x-panel>
            @can('maintenance.manage')
                <x-panel title="Registrar mantenimiento">
                    <form method="POST" action="{{ route('machines.maintenances.store', $machine) }}" class="space-y-3">
                        @csrf
                        <x-select name="type" label="Tipo" :options="$types" required/>
                        <x-input name="date" type="date" label="Fecha" :value="today()->toDateString()" required/>
                        <x-input name="technician" label="Técnico"/>
                        @can('costs.view')<x-input name="cost" label="Costo" inputmode="decimal"/>@endcan
                        <x-textarea name="parts_used" label="Repuestos utilizados" rows="2"/>
                        <x-textarea name="notes" label="Observaciones" rows="2"/>
                        <x-input name="next_maintenance_on" type="date" label="Próximo mantenimiento"/>
                        <x-select name="machine_status" label="Estado de la máquina" :options="$statuses" :value="$machine->status"/>
                        <button class="btn btn-primary w-full">Registrar</button>
                    </form>
                </x-panel>
            @endcan
        </div>

        <x-panel title="Historial de mantenimientos" :padding="false" class="lg:col-span-2">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Técnico</th>@can('costs.view')<th class="num">Costo</th>@endcan<th>Repuestos / observaciones</th></tr></thead>
                <tbody>
                    @forelse ($maintenances as $m)
                        <tr>
                            <td class="tabular-nums">{{ fdate($m->date) }}</td>
                            <td><x-badge :color="['preventive' => 'sky', 'corrective' => 'amber', 'emergency' => 'red'][$m->type] ?? 'stone'">{{ $types[$m->type] ?? $m->type }}</x-badge></td>
                            <td>{{ $m->technician ?? '—' }}</td>
                            @can('costs.view')<td class="num">{{ $m->cost !== null ? money($m->cost) : '—' }}</td>@endcan
                            <td class="text-stone-500">{{ $m->parts_used }} {{ $m->notes ? '· '.$m->notes : '' }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="5" message="Sin mantenimientos registrados."/>
                    @endforelse
                </tbody>
            </table>
            @if ($maintenances->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $maintenances->links() }}</div>@endif
        </x-panel>
    </div>
</x-layouts.app>
