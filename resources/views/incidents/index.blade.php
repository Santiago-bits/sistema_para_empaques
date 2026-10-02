@php
    $priorityColors = ['critical' => 'red', 'high' => 'amber', 'medium' => 'sky', 'low' => 'stone'];
    $statusColors = ['open' => 'amber', 'in_progress' => 'sky', 'resolved' => 'emerald', 'closed' => 'stone'];
@endphp
<x-layouts.app title="Incidentes">
    <x-page-header title="Incidentes" subtitle="Cajones faltantes, errores de peso, transporte, documentación y más.">
        <x-slot:actions>
            @can('incidents.manage')
                <a href="{{ route('incidents.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Registrar incidente</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (\App\Models\Incident::STATUSES as $key => $label)
            <x-stat :label="$label" :value="num($counts[$key] ?? 0)" icon="alert" :color="['open' => 'amber', 'in_progress' => 'sky', 'resolved' => 'brand', 'closed' => 'stone'][$key]" :href="route('incidents.index', ['status' => $key])"/>
        @endforeach
    </div>

    <x-filters>
        <x-select name="status" label="Estado" :options="['active' => 'Abiertos y en curso'] + \App\Models\Incident::STATUSES" :value="$status"/>
        <x-select name="type" label="Tipo" :options="\App\Models\Incident::TYPES" :value="request('type')" placeholder="Todos"/>
        <x-select name="priority" label="Prioridad" :options="\App\Models\Incident::PRIORITIES" :value="request('priority')" placeholder="Todas"/>
        <x-input name="q" label="Buscar" :value="request('q')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Número</th><th>Fecha</th><th>Tipo</th><th>Prioridad</th><th>Estado</th><th>Descripción</th><th>Responsable</th></tr></thead>
        <tbody>
            @forelse ($incidents as $incident)
                <tr>
                    <td><a href="{{ route('incidents.show', $incident) }}" class="link code">{{ $incident->number }}</a></td>
                    <td class="whitespace-nowrap">{{ fdate($incident->occurred_at, true) }}</td>
                    <td>{{ \App\Models\Incident::TYPES[$incident->type] ?? $incident->type }}</td>
                    <td><x-badge :color="$priorityColors[$incident->priority] ?? 'stone'">{{ \App\Models\Incident::PRIORITIES[$incident->priority] ?? $incident->priority }}</x-badge></td>
                    <td><x-badge :color="$statusColors[$incident->status] ?? 'stone'">{{ \App\Models\Incident::STATUSES[$incident->status] ?? $incident->status }}</x-badge></td>
                    <td class="max-w-xs truncate">{{ $incident->description }}</td>
                    <td class="text-stone-500">{{ $incident->responsible?->full_name ?? '—' }}</td>
                </tr>
            @empty
                <x-empty colspan="7" message="No hay incidentes para mostrar."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $incidents->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
