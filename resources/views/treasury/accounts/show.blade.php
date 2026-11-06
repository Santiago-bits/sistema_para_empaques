@php
    [$singular, $plural] = \App\Models\AccountMovement::HOLDERS[$type];
    $types = \App\Models\AccountMovement::TYPES;
    $defaultDirection = old('direction', $type === 'client' ? 'collection' : 'payment');
    $portfolioOptions = $portfolio->mapWithKeys(fn ($c) => [$c->id => $c->bank.' N° '.$c->number.' · '.money($c->amount).' · cobro '.fdate($c->payment_date)])->all();
    $period = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
@endphp
<x-layouts.app :title="'Cuenta corriente · '.$label">
    <x-page-header :title="$label" :subtitle="'Cuenta corriente · '.$singular.($holder->cuit ? ' · CUIT '.$holder->cuit : '')"
                   :back="route('accounts.index', ['type' => $type])">
        <x-slot:actions>
            <a href="{{ route('accounts.print', [$type, $holder->getKey()] + $period) }}" class="btn btn-secondary"><x-icon name="printer" class="size-4"/> Imprimir resumen</a>
            <a href="{{ route('accounts.show', [$type, $holder->getKey()] + $period + ['format' => 'xlsx']) }}" class="btn btn-secondary"><x-icon name="download" class="size-4"/> Excel</a>
        </x-slot:actions>
    </x-page-header>

    @include('treasury.accounts._statement')
</x-layouts.app>
