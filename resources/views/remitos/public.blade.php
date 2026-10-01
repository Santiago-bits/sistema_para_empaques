{{-- Consulta pública por QR: SÓLO datos limitados (sin CUIT, precios ni datos personales). --}}
<x-layouts.guest :title="'Remito '.$remito->number">
    <div class="grid min-h-screen place-items-center p-6">
        <div class="panel w-full max-w-sm p-6">
            <p class="text-xs font-semibold tracking-widest text-brand-600 uppercase">{{ setting('company.name') }}</p>
            <h1 class="mt-1 text-2xl font-semibold">Remito <span class="code">{{ $remito->number }}</span></h1>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-stone-500">Carga</dt><dd class="code">{{ $remito->loadRecord?->number }}</dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Estado</dt><dd><x-status :status="$remito->status"/></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Fecha</dt><dd>{{ fdate($remito->issued_at) }}</dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Destino</dt><dd>{{ $remito->destination ? trim($remito->destination->locality.', '.$remito->destination->province, ', ') : '—' }}</dd></div>
            </dl>
        </div>
    </div>
</x-layouts.guest>
