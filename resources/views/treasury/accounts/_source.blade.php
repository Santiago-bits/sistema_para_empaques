{{-- Enlace al origen de un movimiento de cuenta corriente (factura, lote, carga, cheque, envases). --}}
@php
    $links = [
        'invoice' => ['invoices.show', 'billing.view'],
        'lot' => ['lots.show', 'lots.view'],
        'load' => ['loads.show', 'loads.view'],
        'check' => ['checks.show', 'treasury.view'],
    ];
    $target = $links[$row->source_type] ?? null;
@endphp
@if ($row->source_type === 'container_movement' && Route::has('holders.activity') && auth()->user()->can('containers.view'))
    <a href="{{ route('holders.activity', [$row->holder_type, $row->holder_id, 'kind' => 'containers']) }}" class="link text-xs">Ver envases</a>
@elseif ($target && $row->source_id && Route::has($target[0]) && auth()->user()->can($target[1]))
    <a href="{{ route($target[0], $row->source_id) }}" class="link text-xs">Ver {{ ['invoice' => 'comprobante', 'lot' => 'lote', 'load' => 'carga', 'check' => 'cheque'][$row->source_type] }}</a>
@endif
