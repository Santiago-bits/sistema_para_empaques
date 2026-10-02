@php
    $inCategories = array_intersect_key(\App\Models\CashMovement::CATEGORIES, array_flip(['collection', 'bank_withdrawal', 'owner', 'other']));
    $outCategories = array_diff_key(\App\Models\CashMovement::CATEGORIES, array_flip(['collection', 'bank_withdrawal']));
@endphp
<x-layouts.app title="Caja">
    <x-page-header title="Caja"
                   :subtitle="$session ? 'Abierta el '.fdate($session->opened_at, true).' por '.($session->opener?->full_name ?? '—') : 'Efectivo del galpón: saldo anterior, ingresos, egresos y cierre con arqueo.'">
        <x-slot:actions>
            <a href="{{ route('cash.sessions') }}" class="btn btn-secondary"><x-icon name="list" class="size-4"/> Cierres anteriores</a>
            @if ($session)
                @can('cash.manage')
                    <button type="button" class="btn btn-warning" @click="$dispatch('open-modal', 'close-cash')"><x-icon name="lock" class="size-4"/> Cerrar caja</button>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @include('treasury._nav')

    @if (! $session)
        <x-panel title="La caja está cerrada" class="max-w-xl">
            @can('cash.manage')
                <form method="POST" action="{{ route('cash.open') }}" class="space-y-4">
                    @csrf
                    <x-input name="opening_balance" inputmode="decimal" label="Saldo inicial (efectivo en caja)" :value="num($suggestedOpening, 2)" required autofocus
                             :hint="$lastClosed ? 'Saldo anterior: '.money($lastClosed->counted_balance).' contado al cierre del '.fdate($lastClosed->closed_at, true).'.' : 'Primera apertura: contá el efectivo y cargalo.'"/>
                    <x-input name="notes" label="Observaciones" maxlength="500"/>
                    <button class="btn btn-primary"><x-icon name="check" class="size-4"/> Abrir caja</button>
                </form>
            @else
                <p class="text-sm text-stone-500">No tenés permiso para abrir la caja.</p>
            @endcan
        </x-panel>
    @else
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Saldo anterior" :value="money($session->opening_balance)" icon="archive" color="stone"/>
            <x-stat label="Ingresos" :value="money($totals['in'])" icon="arrow-down" color="brand"/>
            <x-stat label="Egresos" :value="money($totals['out'])" icon="arrow-up" color="red"/>
            <x-stat label="Saldo actual" :value="money($totals['balance'])" icon="currency" color="sky" hint="Efectivo que debería haber"/>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            @can('cash.manage')
                <x-panel title="Registrar movimiento" class="xl:col-span-1">
                    <form method="POST" action="{{ route('cash.movements.store') }}" class="space-y-4"
                          x-data="{ direction: {{ \Illuminate\Support\Js::from(old('direction', 'in')) }} }">
                        @csrf
                        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Tipo de movimiento">
                            <label class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-semibold"
                                   :class="direction === 'in' ? 'border-brand-500 bg-brand-50 text-brand-800 dark:bg-brand-950/40 dark:text-brand-300' : 'border-stone-200 dark:border-stone-700'">
                                <input type="radio" name="direction" value="in" x-model="direction" class="sr-only"> Ingreso
                            </label>
                            <label class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-semibold"
                                   :class="direction === 'out' ? 'border-red-500 bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-300' : 'border-stone-200 dark:border-stone-700'">
                                <input type="radio" name="direction" value="out" x-model="direction" class="sr-only"> Egreso
                            </label>
                        </div>
                        <template x-if="direction === 'in'">
                            <x-select name="category" label="Concepto" :options="$inCategories" :value="old('category')" required/>
                        </template>
                        <template x-if="direction === 'out'">
                            <x-select name="category" label="Concepto" :options="$outCategories" :value="old('category', 'expenses')" required/>
                        </template>
                        <x-input name="description" label="Descripción" required maxlength="255" placeholder="Ej.: compra de cinta para embalar"/>
                        <x-input name="amount" inputmode="decimal" label="Importe" required hint="Ej.: 15.000,50"/>
                        <p class="form-hint">Los cobros y pagos a clientes, productores, transportistas o empleados se registran desde su <a href="{{ route('accounts.index') }}" class="link">cuenta corriente</a> (con «Efectivo» entran a la caja solos).</p>
                        <button class="btn btn-primary w-full">Registrar</button>
                    </form>
                </x-panel>
            @endcan

            <x-table class="xl:col-span-2">
                <thead><tr><th>Hora</th><th>Concepto</th><th>Descripción</th><th class="num">Ingreso</th><th class="num">Egreso</th><th></th></tr></thead>
                <tbody>
                    @forelse ($movements as $m)
                        <tr @class(['opacity-50 line-through' => $m->voided_at])>
                            <td class="whitespace-nowrap tabular-nums">{{ $m->moved_at->format('H:i') }}</td>
                            <td><x-badge :color="$m->direction === 'in' ? 'emerald' : 'red'">{{ \App\Models\CashMovement::CATEGORIES[$m->category] ?? $m->category }}</x-badge></td>
                            <td class="min-w-48">
                                <span class="font-medium text-stone-900 dark:text-white">{{ $m->description }}</span>
                                @if ($m->accountMovement?->holder)
                                    <a href="{{ route('accounts.show', [$m->accountMovement->holder_type, $m->accountMovement->holder_id]) }}" class="link block text-xs">Cuenta de {{ \App\Models\AccountMovement::holderLabel($m->accountMovement->holder) }}</a>
                                @endif
                                <span class="block text-xs text-stone-500">{{ $m->user?->full_name }}@if ($m->voided_at) · Anulado: {{ $m->void_reason }}@endif</span>
                            </td>
                            <td class="num text-emerald-700 dark:text-emerald-400">{{ $m->direction === 'in' ? money($m->amount) : '' }}</td>
                            <td class="num text-red-700 dark:text-red-400">{{ $m->direction === 'out' ? money($m->amount) : '' }}</td>
                            <td class="text-right">
                                @if (! $m->voided_at)
                                    @can('accounts.void')
                                        <x-void-button :action="route('cash.movements.void', $m)" title="¿Anular este movimiento de caja?"/>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="6" message="Todavía no hay movimientos en esta caja."/>
                    @endforelse
                </tbody>
            </x-table>
        </div>

        @can('cash.manage')
            <x-modal name="close-cash" title="Cerrar caja">
                <form method="POST" action="{{ route('cash.close') }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-stone-600 dark:text-stone-300">Saldo esperado: <strong class="tabular-nums">{{ money($totals['balance']) }}</strong>. Contá el efectivo y cargá lo que hay.</p>
                    <x-input name="counted_balance" inputmode="decimal" label="Efectivo contado" required :value="num($totals['balance'], 2)"/>
                    <x-input name="notes" label="Observaciones" maxlength="500" hint="Obligatorio si hay diferencia."/>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'close-cash')">Cancelar</button>
                        <button class="btn btn-warning">Cerrar caja</button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endif
</x-layouts.app>
