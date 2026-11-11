{{-- Pestañas de Tesorería. --}}
@php
    $tabs = [
        'cash.index' => ['Caja', 'currency', 'cash.*'],
        'accounts.index' => ['Cuentas corrientes', 'book', 'accounts.*'],
        'checks.index' => ['Cheques', 'receipt', 'checks.*'],
        'exchange.index' => ['Valor del dólar', 'chart-line', 'exchange.*'],
    ];
@endphp
<nav class="tabs" aria-label="Tesorería">
    @foreach ($tabs as $route => [$label, $icon, $pattern])
        <a href="{{ route($route) }}" @class(['tab', 'active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
            <x-icon :name="$icon" class="size-5"/> {{ $label }}
        </a>
    @endforeach
</nav>
