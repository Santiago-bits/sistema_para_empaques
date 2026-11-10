@php
    $statusColors = ['validated' => 'amber', 'unrecognized' => 'red', 'imported' => 'emerald', 'discarded' => 'zinc'];
    $statusLabels = ['validated' => 'Pendiente de confirmar', 'unrecognized' => 'Sin reconocer', 'imported' => 'Importado', 'discarded' => 'Descartado'];
    $pending = $batches->where('status', 'validated');
    $unrecognized = $batches->where('status', 'unrecognized');
    $toImport = $pending->sum('valid_rows');
@endphp
<x-layouts.app title="Revisar carpeta">
    <x-page-header title="Revisar la carpeta" :subtitle="$batches->count().' planilla(s) · '.fdate($batches->first()->created_at, true).' · '.$batches->first()->user?->full_name" :back="route('imports.index')"/>

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat label="Planillas reconocidas" :value="num($batches->whereIn('status', ['validated', 'imported'])->count())" icon="check" color="brand"/>
        <x-stat label="Sin reconocer" :value="num($unrecognized->count())" icon="alert" :color="$unrecognized->isNotEmpty() ? 'red' : 'stone'"
                :hint="$unrecognized->isNotEmpty() ? 'No se importan hasta que elijas su sección' : null"/>
        <x-stat label="Registros listos para cargar" :value="num($toImport)" icon="list"/>
    </div>

    @if ($pending->isNotEmpty() || $unrecognized->isNotEmpty())
        <div class="panel mb-6 flex flex-wrap items-center justify-between gap-3 p-4">
            <p class="text-sm">
                Revisá a qué sección va cada planilla. Si alguna está mal, cambiala en su fila antes de confirmar.
                @if ($unrecognized->isNotEmpty()) Las planillas <strong>sin reconocer</strong> no se cargan. @endif
                Al confirmar se cargan primero transportistas, clientes y cuadrillas, y cada planilla se vuelve a revisar: un camión cuyo
                transportista viene en otra planilla de esta carpeta ya lo encuentra.
            </p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('imports.folder.discard', $group) }}" x-data x-confirm="¿Descartar todas las planillas pendientes? No se guarda nada.">
                    @csrf
                    <button class="btn btn-secondary">Descartar todo</button>
                </form>
                @if ($pending->isNotEmpty())
                    <form method="POST" action="{{ route('imports.folder.confirm', $group) }}" x-data
                          x-confirm="¿Confirmás la carga de {{ $pending->count() }} planilla(s), cada una en su sección?">
                        @csrf
                        <button class="btn btn-primary"><x-icon name="check" class="size-4"/> Confirmar e importar</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <x-table>
        <thead><tr><th>Planilla</th><th>Va a</th><th>Por qué</th><th class="num">Correctas</th><th class="num">Errores</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @foreach ($batches as $batch)
                @php $detection = $batch->detection ?? []; @endphp
                <tr>
                    <td class="max-w-xs truncate font-medium" title="{{ $batch->filename }}">{{ $batch->filename }}</td>
                    <td>
                        @if (in_array($batch->status, ['validated', 'unrecognized'], true))
                            <form method="POST" action="{{ route('imports.retype', $batch) }}" class="flex items-center gap-2">
                                @csrf
                                <select name="type" class="form-input min-w-44 py-1 text-sm" aria-label="Sección de {{ $batch->filename }}" required>
                                    <option value="">Elegir sección…</option>
                                    @foreach ($types as $key => $label)
                                        <option value="{{ $key }}" @selected($batch->type === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-secondary btn-sm">{{ $batch->type ? 'Cambiar' : 'Usar' }}</button>
                            </form>
                        @else
                            {{ $types[$batch->type] ?? '—' }}
                        @endif
                    </td>
                    <td class="text-sm text-stone-600 dark:text-stone-400">
                        {{ $detection['reason'] ?? '' }}
                        @if (($detection['special'] ?? null) === 'dtv' && Route::has('dtv.index'))
                            <a href="{{ route('dtv.index') }}" class="link">Ir a DTV-e</a>
                        @endif
                    </td>
                    <td class="num text-emerald-700 dark:text-emerald-400">{{ $batch->type ? num($batch->valid_rows) : '—' }}</td>
                    <td class="num {{ $batch->error_rows ? 'text-red-600' : '' }}">{{ $batch->type ? num($batch->error_rows) : '—' }}</td>
                    <td><x-badge :color="$statusColors[$batch->status] ?? 'stone'">{{ $statusLabels[$batch->status] ?? $batch->status }}</x-badge></td>
                    <td class="text-right">
                        @if ($batch->type)
                            <a href="{{ route('imports.show', $batch) }}" class="link">Ver filas</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
</x-layouts.app>
