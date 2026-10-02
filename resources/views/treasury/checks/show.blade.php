@php
    $actions = [
        'deposited' => ['Depositar', 'btn-secondary', false],
        'cashed' => ['Marcar cobrado', 'btn-primary', false],
        'paid' => ['Marcar debitado', 'btn-primary', false],
        'rejected' => ['Rechazado / devuelto', 'btn-danger', true],
        'voided' => ['Anular', 'btn-ghost', true],
    ];
    $days = $check->daysToPayment();
@endphp
<x-layouts.app :title="'Cheque '.$check->bank.' N° '.$check->number">
    <x-page-header :title="'Cheque '.$check->bank.' N° '.$check->number" :subtitle="\App\Models\Check::KINDS[$check->kind].($check->electronic ? ' · e-cheq' : '')"
                   :back="route('checks.index')">
        <x-slot:actions><x-badge :color="$check->statusColor()" class="text-sm">{{ $check->statusLabel() }}</x-badge></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Datos">
                <x-dl :items="[
                    'Importe' => money($check->amount),
                    'Fecha de emisión' => fdate($check->issued_on),
                    'Fecha de cobro' => fdate($check->payment_date).(in_array($check->status, ['in_portfolio', 'deposited', 'issued'], true) ? ' ('.($days > 0 ? 'faltan '.$days.' días' : ($days === 0 ? 'hoy' : 'vencido hace '.abs($days).' días')).')' : ''),
                    'Librador' => trim(($check->issuer_name ?? '').($check->issuer_cuit ? ' · CUIT '.$check->issuer_cuit : '')) ?: null,
                    'Recibido de' => $check->receivedFrom ? \App\Models\AccountMovement::holderLabel($check->receivedFrom) : null,
                    'Entregado a' => $check->deliveredTo ? \App\Models\AccountMovement::holderLabel($check->deliveredTo) : null,
                    'Último cambio' => $check->status_date ? fdate($check->status_date) : null,
                    'Cargado por' => $check->creator?->full_name,
                    'Observaciones' => $check->notes,
                ]"/>
            </x-panel>

            <x-panel title="Movimientos en cuentas corrientes">
                @forelse ($movements as $m)
                    <div @class(['flex items-center justify-between gap-3 border-b border-stone-100 py-2 text-sm last:border-0 dark:border-stone-800', 'opacity-50 line-through' => $m->isVoided()])>
                        <div>
                            <a href="{{ route('accounts.show', [$m->holder_type, $m->holder_id]) }}" class="link">{{ $m->holder ? \App\Models\AccountMovement::holderLabel($m->holder) : '—' }}</a>
                            <span class="block text-xs text-stone-500">{{ fdate($m->date) }} · {{ $m->description }}</span>
                        </div>
                        <span class="tabular-nums">{{ (float) $m->debit ? 'Debe '.money($m->debit) : 'Haber '.money($m->credit) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Este cheque no está vinculado a ninguna cuenta corriente.</p>
                @endforelse
            </x-panel>
        </div>

        @can('checks.manage')
            <x-panel title="Acciones">
                @php $available = array_filter($actions, fn ($a, $status) => $check->canMoveTo($status), ARRAY_FILTER_USE_BOTH); @endphp
                @forelse ($available as $status => [$label, $class, $needsReason])
                    <form method="POST" action="{{ route('checks.transition', $check) }}" class="mb-3 space-y-2" x-data="{ open: {{ $needsReason ? 'false' : 'true' }} }">
                        @csrf
                        <input type="hidden" name="status" value="{{ $status }}">
                        @if ($needsReason)
                            <button type="button" class="btn {{ $class }} w-full" x-show="!open" @click="open = true; $nextTick(() => $refs.notes.focus())">{{ $label }}</button>
                            <div x-show="open" x-cloak class="space-y-2 rounded-lg border border-stone-200 p-2 dark:border-stone-700">
                                <input x-ref="notes" name="notes" required maxlength="255" class="form-input" placeholder="Motivo (obligatorio)">
                                <div class="flex gap-2">
                                    <button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancelar</button>
                                    <button class="btn {{ $class }} btn-sm flex-1">Confirmar: {{ mb_strtolower($label) }}</button>
                                </div>
                            </div>
                        @else
                            <div class="flex gap-2">
                                <input type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="form-input w-40" aria-label="Fecha">
                                <button class="btn {{ $class }} flex-1">{{ $label }}</button>
                            </div>
                        @endif
                    </form>
                @empty
                    <p class="text-sm text-stone-500">El cheque está {{ mb_strtolower($check->statusLabel()) }}: no tiene más acciones.</p>
                @endforelse
                @if ($check->kind === 'third_party' && $check->status === 'in_portfolio')
                    <p class="form-hint mt-2">Para <strong>endosarlo</strong>, registrá un pago con cheque desde la cuenta corriente del proveedor, productor o transportista.</p>
                @endif
                @if ($check->status === 'rejected' || in_array('rejected', \App\Models\Check::TRANSITIONS[$check->kind][$check->status] ?? [], true))
                    <p class="form-hint mt-2">Si se rechaza, el importe vuelve a la deuda del que lo entregó en su cuenta corriente.</p>
                @endif
            </x-panel>
        @endcan
    </div>
</x-layouts.app>
