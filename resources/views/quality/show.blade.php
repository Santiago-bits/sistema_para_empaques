@php
    $target = $control->crate ? ['Cajón', $control->crate->code, route('crates.show', $control->crate)]
        : ($control->pallet ? ['Pallet', $control->pallet->code, route('pallets.show', $control->pallet)]
        : ($control->lot ? ['Lote', $control->lot->code, route('lots.show', $control->lot)] : ['—', '—', null]));
    $color = ['approved' => 'emerald', 'rejected' => 'red', 'observed' => 'amber'][$control->result] ?? 'stone';
@endphp
<x-layouts.app title="Control de calidad">
    <x-page-header :title="'Control de calidad · '.$target[0].' '.$target[1]" :subtitle="fdate($control->controlled_at, true).' · '.$control->user?->full_name" :back="route('quality.index')">
        <x-slot:actions>
            <x-badge :color="$color" class="text-sm">{{ \App\Models\QualityControl::RESULTS[$control->result] ?? $control->result }}</x-badge>
            @if ($target[2])<a href="{{ $target[2] }}" class="btn btn-secondary">Ver {{ mb_strtolower($target[0]) }}</a>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="% daños" :value="pct($control->damage_pct)" icon="alert" color="amber"/>
        <x-stat label="% golpes" :value="pct($control->bruise_pct)" icon="alert" color="amber"/>
        <x-stat label="% podredumbre" :value="pct($control->rot_pct)" icon="alert" color="red"/>
        <x-stat label="% rechazo" :value="pct($control->reject_pct)" icon="trash" color="red"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Detalle" class="lg:col-span-2">
            <x-dl :items="[
                'Calidad' => $control->grade,
                'Calibre' => $control->caliber,
                'Maduración' => $control->ripeness,
                'Variedad' => $control->crate?->variety?->name ?? $control->lot?->variety?->name ?? $control->pallet?->variety?->name,
                'Tamaño' => $control->crate?->size?->name,
                'Embalador' => $control->crate?->packer?->full_name,
                'Lote' => $control->crate?->lot?->code ?? $control->lot?->code ?? $control->pallet?->lot?->code,
                'Productor' => $control->lot?->producer?->name,
                'Defectos' => $control->defects,
                'Observaciones' => $control->notes,
            ]"/>
        </x-panel>

        <x-panel title="Rechazos registrados" :padding="false">
            <table class="table">
                <thead><tr><th>Cajón</th><th>Motivo</th><th class="num">Kg</th></tr></thead>
                <tbody>
                    @forelse ($control->rejects as $reject)
                        <tr>
                            <td class="code">{{ $reject->crate?->code ?? '—' }}</td>
                            <td>{{ $reject->reason?->name }}</td>
                            <td class="num">{{ num($reject->weight, 2) }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="3" message="Sin rechazos asociados."/>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </div>
</x-layouts.app>
