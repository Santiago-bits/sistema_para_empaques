@php $errorsList = $batch->errors ?? []; @endphp
<x-layouts.app title="Importación">
    <x-page-header :title="'Importación de '.mb_strtolower($typeLabel)" :subtitle="$batch->filename.' · '.fdate($batch->created_at, true).' · '.$batch->user?->full_name" :back="route('imports.index')"/>

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat label="Registros encontrados" :value="num($batch->total_rows)" icon="list"/>
        <x-stat label="Correctos" :value="num($batch->valid_rows)" icon="check" color="brand"
                :hint="$batch->mode === 'upsert' ? num($batch->valid_rows - $batch->updated_rows).' nuevos · '.num($batch->updated_rows).' a actualizar' : null"/>
        <x-stat label="Con errores" :value="num($batch->error_rows)" icon="alert" :color="$batch->error_rows ? 'red' : 'stone'"/>
    </div>

    @if ($batch->status === 'validated')
        <div class="panel mb-6 flex flex-wrap items-center justify-between gap-3 p-4">
            <p class="text-sm">
                @if ($batch->valid_rows > 0)
                    @if ($batch->mode === 'upsert')
                        Se agregarán <strong>{{ num($batch->valid_rows - $batch->updated_rows) }}</strong> y se actualizarán <strong>{{ num($batch->updated_rows) }}</strong> registro(s) existentes (sólo las columnas que trae el archivo).
                    @else
                        Se importarán <strong>{{ num($batch->valid_rows) }}</strong> registro(s).
                    @endif
                    @if ($batch->error_rows) Las {{ num($batch->error_rows) }} fila(s) con errores se omitirán. @endif
                @else
                    No hay filas válidas para importar. Corregí el archivo y volvé a subirlo.
                @endif
            </p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('imports.discard', $batch) }}" x-data x-confirm="¿Descartar esta importación?">
                    @csrf
                    <button class="btn btn-secondary">Descartar</button>
                </form>
                @if ($batch->valid_rows > 0)
                    <form method="POST" action="{{ route('imports.confirm', $batch) }}" x-data x-confirm="¿Confirmás la importación de {{ $batch->valid_rows }} registro(s)?">
                        @csrf
                        <button class="btn btn-primary"><x-icon name="check" class="size-4"/> Confirmar importación</button>
                    </form>
                @endif
            </div>
        </div>
    @else
        <div class="mb-6">
            <x-badge :color="$batch->status === 'imported' ? 'emerald' : 'zinc'" class="text-sm">
                {{ $batch->status === 'imported' ? 'Importado el '.fdate($batch->imported_at, true) : 'Descartado' }}
            </x-badge>
        </div>
    @endif

    @if ($preview['rows'])
        <x-panel title="Vista previa (primeras filas)" :padding="false" class="mb-6">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Fila</th>@foreach ($preview['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                <td class="tabular-nums text-stone-400">{{ $row['row'] }}</td>
                                @foreach ($row['cells'] as $cell)
                                    <td>{{ $cell instanceof \DateTimeInterface ? fdate($cell) : $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    <x-panel title="Errores" :padding="false">
        <x-slot:actions>
            @if ($errorsList)
                <a href="{{ route('imports.errors', $batch) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4"/> Descargar errores</a>
            @endif
        </x-slot:actions>
        <table class="table">
            <thead><tr><th class="w-20">Fila</th><th class="w-48">Campo</th><th>Error</th></tr></thead>
            <tbody>
                @forelse (array_slice($errorsList, 0, 200) as $error)
                    <tr>
                        <td class="tabular-nums">{{ $error['row'] ?? '' }}</td>
                        <td class="code">{{ $error['field'] ?? '' }}</td>
                        <td class="text-red-700 dark:text-red-400">{{ $error['message'] ?? '' }}</td>
                    </tr>
                @empty
                    <x-empty colspan="3" message="Sin errores de validación."/>
                @endforelse
            </tbody>
        </table>
        @if (count($errorsList) > 200)
            <p class="border-t border-stone-200 px-4 py-2 text-xs text-stone-500 dark:border-stone-800">Se muestran 200 de {{ count($errorsList) }} errores. Descargá el CSV para verlos todos.</p>
        @endif
    </x-panel>
</x-layouts.app>
