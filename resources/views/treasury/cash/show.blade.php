<x-layouts.app title="Caja del {{ fdate($session->opened_at) }}">
    <x-page-header :title="'Caja del '.fdate($session->opened_at)"
                   :subtitle="$session->isOpen() ? 'Abierta' : 'Cerrada el '.fdate($session->closed_at, true).' por '.($session->closer?->full_name ?? '—')"
                   :back="route('cash.sessions')">
        <x-slot:actions>
            <button type="button" class="btn btn-secondary" onclick="window.print()"><x-icon name="printer" class="size-4"/> Imprimir</button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat label="Saldo inicial" :value="money($session->opening_balance)" color="stone"/>
        <x-stat label="Ingresos" :value="money($totals['in'])" color="brand"/>
        <x-stat label="Egresos" :value="money($totals['out'])" color="red"/>
        <x-stat label="Esperado" :value="money($session->expected_balance ?? $totals['balance'])" color="sky"/>
        <x-stat label="Contado / diferencia" :value="$session->counted_balance !== null ? money($session->counted_balance) : '—'"
                :hint="$session->difference !== null ? 'Diferencia '.money($session->difference) : null" :color="$session->difference !== null && abs((float) $session->difference) > 0.004 ? 'amber' : 'stone'"/>
    </div>

    @if ($session->notes)
        <x-panel title="Observaciones" class="mb-6"><p class="text-sm whitespace-pre-line">{{ $session->notes }}</p></x-panel>
    @endif

    <x-table>
        <thead><tr><th>Fecha y hora</th><th>Concepto</th><th>Descripción</th><th>Usuario</th><th class="num">Ingreso</th><th class="num">Egreso</th></tr></thead>
        <tbody>
            @forelse ($movements as $m)
                <tr @class(['opacity-50 line-through' => $m->voided_at])>
                    <td class="whitespace-nowrap">{{ fdate($m->moved_at, true) }}</td>
                    <td>{{ \App\Models\CashMovement::CATEGORIES[$m->category] ?? $m->category }}</td>
                    <td>{{ $m->description }}@if ($m->voided_at) <span class="block text-xs">Anulado: {{ $m->void_reason }}</span>@endif</td>
                    <td class="text-stone-500">{{ $m->user?->full_name }}</td>
                    <td class="num">{{ $m->direction === 'in' ? money($m->amount) : '' }}</td>
                    <td class="num">{{ $m->direction === 'out' ? money($m->amount) : '' }}</td>
                </tr>
            @empty
                <x-empty colspan="6" message="Sin movimientos."/>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
