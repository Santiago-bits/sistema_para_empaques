@php
    $statuses = \App\Models\Incident::STATUSES;
    $statusColors = ['open' => 'amber', 'in_progress' => 'sky', 'resolved' => 'emerald', 'closed' => 'stone'];
    $related = $incident->related;
    $relatedLink = match (true) {
        $related instanceof \App\Models\Crate => ['Cajón '.$related->code, route('crates.show', $related), 'crates.view'],
        $related instanceof \App\Models\Pallet => ['Pallet '.$related->code, route('pallets.show', $related), 'pallets.view'],
        $related instanceof \App\Models\Load => ['Carga '.$related->number, route('loads.show', $related), 'loads.view'],
        default => null,
    };
    $events = $incident->stateHistories->map(fn ($h) => [
        'time' => $h->created_at,
        'title' => ($h->from_state ? ($statuses[$h->from_state] ?? $h->from_state).' → ' : '').($statuses[$h->to_state] ?? $h->to_state),
        'detail' => $h->notes,
        'user' => $h->user?->full_name,
        'color' => ['open' => 'amber', 'in_progress' => 'sky', 'resolved' => 'brand', 'closed' => 'stone'][$h->to_state] ?? 'stone',
    ])->all();
@endphp
<x-layouts.app :title="'Incidente '.$incident->number">
    <x-page-header :title="'Incidente '.$incident->number" :subtitle="\App\Models\Incident::TYPES[$incident->type] ?? $incident->type" :back="route('incidents.index')">
        <x-slot:actions>
            <x-badge :color="$statusColors[$incident->status] ?? 'stone'">{{ $statuses[$incident->status] }}</x-badge>
            @can('incidents.manage')
                <a href="{{ route('incidents.edit', $incident) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel>
                <x-dl :items="[
                    'Fecha y hora' => fdate($incident->occurred_at, true),
                    'Prioridad' => \App\Models\Incident::PRIORITIES[$incident->priority] ?? $incident->priority,
                    'Sector' => $incident->area,
                    'Responsable' => $incident->responsible?->full_name,
                    'Registrado por' => $incident->reporter?->full_name,
                    'Resuelto' => $incident->resolved_at ? fdate($incident->resolved_at, true) : null,
                ]">
                    @if ($relatedLink)
                        <div>
                            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">Vinculado a</dt>
                            <dd class="mt-0.5">@can($relatedLink[2])<a href="{{ $relatedLink[1] }}" class="link code">{{ $relatedLink[0] }}</a>@else<span class="code">{{ $relatedLink[0] }}</span>@endcan</dd>
                        </div>
                    @endif
                </x-dl>
                <h3 class="mt-5 mb-1 text-xs font-semibold tracking-wide text-stone-500 uppercase">Descripción</h3>
                <p class="text-sm whitespace-pre-line">{{ $incident->description }}</p>
                @if ($incident->resolution)
                    <h3 class="mt-5 mb-1 text-xs font-semibold tracking-wide text-stone-500 uppercase">Resolución</h3>
                    <p class="text-sm whitespace-pre-line">{{ $incident->resolution }}</p>
                @endif
            </x-panel>

            @can('incidents.manage')
                @if ($transitions)
                    <x-panel title="Cambiar estado">
                        <form method="POST" action="{{ route('incidents.status', $incident) }}" class="space-y-3">
                            @csrf
                            <x-textarea name="resolution" label="Resolución / comentario" rows="3" maxlength="2000" hint="Obligatorio para marcar como resuelto o cerrado."/>
                            <div class="flex flex-wrap justify-end gap-2">
                                @foreach ($transitions as $to)
                                    <button name="status" value="{{ $to }}" @class(['btn', 'btn-primary' => in_array($to, ['resolved', 'closed'], true), 'btn-secondary' => ! in_array($to, ['resolved', 'closed'], true)])>
                                        {{ ['open' => 'Reabrir', 'in_progress' => 'Pasar a en curso', 'resolved' => 'Marcar resuelto', 'closed' => 'Cerrar'][$to] }}
                                    </button>
                                @endforeach
                            </div>
                        </form>
                    </x-panel>
                @endif
            @endcan
        </div>

        <x-panel title="Historial">
            <x-timeline :events="$events"/>
        </x-panel>
    </div>
</x-layouts.app>
