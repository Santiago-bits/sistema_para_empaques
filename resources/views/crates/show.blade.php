<x-layouts.app :title="'Cajón '.$crate->code">
    <x-page-header :title="'Cajón '.$crate->code" :subtitle="$crate->processed_at ? 'Procesado '.fdate($crate->processed_at, true) : 'Sin procesar'" :back="route('crates.index')">
        <x-slot:actions>
            <x-status :status="$crate->status" class="text-sm"/>
            <x-badge :color="['approved' => 'emerald', 'rejected' => 'red'][$crate->quality_status] ?? 'stone'" class="text-sm">
                Calidad: {{ \App\Models\Crate::QUALITY_STATUSES[$crate->quality_status] ?? $crate->quality_status }}
            </x-badge>
            @can('labels.print')
                <a href="{{ route('labels.crates', ['ids' => [$crate->id]]) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" class="size-4"/> Etiqueta</a>
            @endcan
            @unless ($locked)
                @can('crates.void')
                    <button type="button" class="btn btn-ghost text-red-600" @click="$dispatch('open-modal', 'void-crate')">Anular</button>
                @endcan
                @can('crates.update')
                    <a href="{{ route('crates.edit', $crate) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
                @endcan
            @endunless
        </x-slot:actions>
    </x-page-header>

    @include('traceability._trace', ['chain' => $chain, 'timeline' => $timeline])

    @can('crates.void')
        <x-modal name="void-crate" title="Anular cajón {{ $crate->code }}">
            <form method="POST" action="{{ route('crates.void', $crate) }}" class="space-y-4" x-data x-confirm="¿Confirmás la anulación? La producción del cajón deja de contar en estadísticas.">
                @csrf
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'void-crate')">Cancelar</button>
                    <button class="btn btn-danger">Anular cajón</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
