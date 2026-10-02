@php
    $views = \App\Http\Controllers\Treasury\CheckController::VIEWS;
    $warningDays = (int) setting('treasury.check_warning_days', 7);
@endphp
<x-layouts.app title="Cheques">
    <x-page-header title="Cheques" subtitle="Cartera de cheques de terceros y cheques propios emitidos.">
        <x-slot:actions>
            @can('checks.manage')
                <a href="{{ route('checks.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo cheque</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('treasury._nav')

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="En cartera" :value="money($portfolio)" icon="archive" color="sky" :href="route('checks.index', ['view' => 'portfolio'])"/>
        <x-stat label="Depositados (a acreditar)" :value="money($deposited)" icon="bank" color="violet" :href="route('checks.index', ['view' => 'receivable'])"/>
        <x-stat label="Propios por pagar" :value="money($issued)" icon="receipt" color="amber" :href="route('checks.index', ['view' => 'payable'])"/>
        <x-stat label="Vencen en {{ $warningDays }} días o menos" :value="num($dueSoon)" icon="clock" :color="$dueSoon > 0 ? 'red' : 'stone'" :href="route('checks.index', ['view' => 'due'])"/>
    </div>

    <nav class="mb-4 flex flex-wrap gap-1" aria-label="Vistas de cheques">
        @foreach ($views as $key => $name)
            <a href="{{ route('checks.index', ['view' => $key]) }}" @class(['rounded-full px-3 py-1 text-sm', 'bg-brand-600 font-medium text-white' => $view === $key, 'bg-stone-100 text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300' => $view !== $key])>{{ $name }}</a>
        @endforeach
    </nav>

    <x-filters :exports="[['label' => 'Excel', 'format' => 'xlsx', 'route' => route('checks.index')]]">
        <input type="hidden" name="view" value="{{ $view }}">
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Número, banco, librador, CUIT"/>
        <x-input name="from" type="date" label="Cobro desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Cobro hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Fecha de cobro</th><th class="num">Días</th><th>Banco / N°</th><th>Librador</th><th>Tipo</th><th>Estado</th><th>Recibido de / entregado a</th><th class="num">Importe</th></tr></thead>
        <tbody>
            @forelse ($checks as $c)
                @php $days = $c->daysToPayment(); $pending = in_array($c->status, ['in_portfolio', 'deposited', 'issued'], true); @endphp
                <tr>
                    <td class="whitespace-nowrap">{{ fdate($c->payment_date) }}</td>
                    <td class="num">
                        @if ($pending)
                            <span @class(['font-semibold', 'text-red-600 dark:text-red-400' => $days <= 0, 'text-amber-600 dark:text-amber-400' => $days > 0 && $days <= $warningDays])>
                                {{ $days <= 0 ? ($days === 0 ? 'Hoy' : 'Vencido') : $days }}
                            </span>
                        @else
                            <span class="text-stone-400">—</span>
                        @endif
                    </td>
                    <td><a href="{{ route('checks.show', $c) }}" class="link">{{ $c->bank }}</a> <span class="code text-xs">N° {{ $c->number }}</span>@if ($c->electronic) <x-badge color="indigo">e-cheq</x-badge>@endif</td>
                    <td class="text-stone-600 dark:text-stone-300">{{ $c->issuer_name ?? '—' }}</td>
                    <td>{{ \App\Models\Check::KINDS[$c->kind] }}</td>
                    <td><x-badge :color="$c->statusColor()">{{ $c->statusLabel() }}</x-badge></td>
                    <td class="text-xs text-stone-500">
                        @if ($c->receivedFrom) De: {{ \App\Models\AccountMovement::holderLabel($c->receivedFrom) }}<br>@endif
                        @if ($c->deliveredTo) A: {{ \App\Models\AccountMovement::holderLabel($c->deliveredTo) }}@endif
                    </td>
                    <td class="num font-medium">{{ money($c->amount) }}</td>
                </tr>
            @empty
                <x-empty colspan="8" message="No hay cheques en esta vista."/>
            @endforelse
        </tbody>
        <x-slot:footer>
            <div class="mb-2 text-right text-sm">Total de la vista: <strong class="tabular-nums">{{ money($viewTotal) }}</strong></div>
            {{ $checks->links() }}
        </x-slot:footer>
    </x-table>
</x-layouts.app>
