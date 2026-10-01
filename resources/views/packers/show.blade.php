@php
    $canManage = auth()->user()->can('packers.manage');
@endphp
<x-layouts.app :title="$record->full_name">
    <x-page-header :title="$record->code.' — '.$record->full_name" subtitle="Embalador" :back="$definition->route('index')">
        <x-slot:actions>
            <a href="{{ route('packers.badge', $record) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" class="size-4"/> Credencial</a>
            @if ($canManage)
                <form method="POST" action="{{ $definition->route('toggle', $record->getKey()) }}" x-data x-confirm="¿{{ $definition->toggleLabel($record) }} al embalador?">
                    @csrf @method('PATCH')
                    <button class="btn btn-secondary">{{ $definition->toggleLabel($record) }}</button>
                </form>
                <a href="{{ $definition->route('edit', $record->getKey()) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Cajones hoy" :value="num($summary['today']['crates'])" icon="box"/>
        <x-stat label="Kg hoy" :value="kg($summary['today']['kg'], 1)" icon="scale" color="accent"/>
        <x-stat label="Cajones del mes" :value="num($summary['month']['crates'])" icon="box" color="sky"/>
        <x-stat label="Kg del mes" :value="kg($summary['month']['kg'], 1)" icon="scale" color="violet"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Datos" class="lg:col-span-2">
            <x-dl :items="[
                'Código' => $record->code,
                'DNI' => $record->dni,
                'Turno' => $record->shift?->name,
                'Ingreso' => fdate($record->hired_on),
                'Estado' => $record->active ? 'Activo' : 'Inactivo',
                'Observaciones' => $record->notes,
            ]"/>
        </x-panel>
        <x-panel title="Últimos 7 días" :padding="false">
            <table class="table">
                <thead><tr><th>Día</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                <tbody>
                    @foreach (array_reverse($summary['days']) as $day)
                        <tr>
                            <td class="tabular-nums">{{ $day['date']->translatedFormat('D d/m') }}</td>
                            <td class="num">{{ num($day['crates']) }}</td>
                            <td class="num">{{ num($day['kg'], 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
    </div>
</x-layouts.app>
