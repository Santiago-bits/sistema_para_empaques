{{-- Pestañas de Tesorería. --}}
@php
    $tabs = [
        'cash.index' => ['Caja', 'currency', 'cash.*'],
        'accounts.index' => ['Cuentas corrientes', 'book', 'accounts.*'],
        'checks.index' => ['Cheques', 'receipt', 'checks.*'],
        'exchange.index' => ['Valor del dólar', 'chart-line', 'exchange.*'],
    ];
@endphp
<nav class="mb-6 flex flex-wrap gap-1 border-b border-stone-200 dark:border-stone-800" aria-label="Tesorería">
    @foreach ($tabs as $route => [$label, $icon, $pattern])
        <a href="{{ route($route) }}" @class([
            '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap',
            'border-brand-600 font-medium text-brand-700 dark:text-brand-400' => request()->routeIs($pattern),
            'border-transparent text-stone-500 hover:text-stone-800 dark:hover:text-stone-200' => ! request()->routeIs($pattern),
        ]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
            <x-icon :name="$icon" class="size-4"/> {{ $label }}
        </a>
    @endforeach
</nav>
