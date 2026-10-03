@php
    $statusColors = ['validated' => 'amber', 'imported' => 'emerald', 'failed' => 'red', 'discarded' => 'zinc'];
    $statusLabels = ['validated' => 'Pendiente de confirmar', 'imported' => 'Importado', 'failed' => 'Fallido', 'discarded' => 'Descartado'];
@endphp
<x-layouts.app title="Planillas subidas">
    <x-page-header title="Planillas subidas" subtitle="Cargá muchos datos de una vez desde Excel. Nada se guarda hasta que confirmes lo que te muestra." :back="route('transfer.index')">
        <x-slot:actions>
            <a href="{{ route('imports.create') }}" class="btn btn-primary"><x-icon name="upload" class="size-4"/> Subir una planilla</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel title="Planillas modelo (vacías, para completar)" class="mb-6">
        <div class="flex flex-wrap gap-2">
            @foreach ($types as $type => $label)
                <a href="{{ route('imports.template', $type) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4"/> {{ $label }}</a>
            @endforeach
        </div>
    </x-panel>

    <x-table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Archivo</th><th class="num">Filas</th><th class="num">Correctas</th><th class="num">Errores</th><th>Estado</th><th>Usuario</th><th></th></tr></thead>
        <tbody>
            @forelse ($batches as $batch)
                <tr>
                    <td class="tabular-nums">{{ fdate($batch->created_at, true) }}</td>
                    <td>{{ $types[$batch->type] ?? $batch->type }}</td>
                    <td class="max-w-xs truncate">{{ $batch->filename }}</td>
                    <td class="num">{{ num($batch->total_rows) }}</td>
                    <td class="num text-emerald-700 dark:text-emerald-400">{{ num($batch->valid_rows) }}</td>
                    <td class="num {{ $batch->error_rows ? 'text-red-600' : '' }}">{{ num($batch->error_rows) }}</td>
                    <td><x-badge :color="$statusColors[$batch->status] ?? 'stone'">{{ $statusLabels[$batch->status] ?? $batch->status }}</x-badge></td>
                    <td>{{ $batch->user?->full_name }}</td>
                    <td class="text-right"><a href="{{ route('imports.show', $batch) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="9" message="Todavía no se realizaron importaciones."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $batches->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
