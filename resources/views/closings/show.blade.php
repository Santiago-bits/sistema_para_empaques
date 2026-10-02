@php
    $details = [
        'Estado' => $closing->reopened_at ? 'Reabierto' : 'Cerrado',
        'Cerrado por' => ($closing->closer?->full_name ?? '—').' · '.fdate($closing->closed_at, true),
        'Reabierto por' => $closing->reopened_at ? ($closing->reopener?->full_name ?? '—').' · '.fdate($closing->reopened_at, true) : null,
        'Motivo de reapertura' => $closing->reopen_reason,
        'Observaciones' => $closing->notes,
        'Resumen generado' => isset($closing->snapshot['generated_at']) ? fdate(\Illuminate\Support\Carbon::parse($closing->snapshot['generated_at']), true) : null,
    ];
@endphp
<x-layouts.app :title="'Cierre '.$closing->date->format('d/m/Y')">
    <x-page-header :title="'Cierre del '.$closing->date->format('d/m/Y')" :back="route('closings.index')">
        <x-slot:actions>
            @if ($closing->reopened_at)
                <a href="{{ route('closings.create', ['date' => $closing->date->toDateString()]) }}" class="btn btn-primary"><x-icon name="lock" class="size-4"/> Volver a cerrar</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-panel class="mb-6">
        <x-dl :items="$details"/>
    </x-panel>

    @include('closings._summary', ['s' => $closing->snapshot])

    @if (! $closing->reopened_at)
        @can('closings.reopen')
            <x-panel title="Reabrir cierre" class="mt-6">
                <form method="POST" action="{{ route('closings.reopen', $closing) }}" x-data x-confirm="¿Reabrir el cierre del {{ $closing->date->format('d/m/Y') }}? Queda registrado en auditoría." class="space-y-4">
                    @csrf
                    <x-input name="reason" label="Motivo" required minlength="5" maxlength="255"/>
                    <div class="flex justify-end">
                        <button class="btn btn-warning">Reabrir</button>
                    </div>
                </form>
            </x-panel>
        @endcan
    @endif
</x-layouts.app>
