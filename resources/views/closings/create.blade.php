<x-layouts.app title="Cerrar día">
    <x-page-header :title="'Cierre del '.$date->format('d/m/Y')" subtitle="Vista previa calculada ahora. Al cerrar, este resumen queda guardado y no cambia." :back="route('closings.index')"/>

    <form method="GET" class="panel mb-6 flex flex-wrap items-end gap-3 p-4" data-allow-resubmit>
        <div>
            <label class="form-label" for="date">Día</label>
            <input type="date" id="date" name="date" class="form-input" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}">
        </div>
        <button class="btn btn-secondary">Ver resumen</button>
    </form>

    @include('closings._summary', ['s' => $snapshot])

    <div class="mt-6">
        @if ($closed)
            <x-panel>
                <p class="text-sm text-stone-600 dark:text-stone-300">Este día ya está cerrado. Para recalcularlo, reabrilo desde el historial de cierres.</p>
            </x-panel>
        @else
            <x-panel title="Confirmar cierre">
                <form method="POST" action="{{ route('closings.store') }}" x-data x-confirm="¿Confirmás el cierre del {{ $date->format('d/m/Y') }}? El resumen quedará guardado." class="space-y-4">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <x-textarea name="notes" label="Observaciones (opcional)" rows="2" maxlength="1000"/>
                    <div class="flex justify-end">
                        <button class="btn btn-primary"><x-icon name="lock" class="size-4"/> Cerrar día</button>
                    </div>
                </form>
            </x-panel>
        @endif
    </div>
</x-layouts.app>
