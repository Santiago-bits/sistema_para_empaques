{{-- Pestañas del panel de desarrollador. --}}
<nav class="mb-6 flex flex-wrap gap-1 border-b border-stone-200 dark:border-stone-800" aria-label="Panel de desarrollador">
    @foreach (['developer.index' => 'Estado', 'developer.errors' => 'Errores', 'developer.logs' => 'Logs', 'developer.licenses.index' => 'Licencias'] as $route => $label)
        @php $active = request()->routeIs($route) || request()->routeIs(str_replace('.index', '', $route).'.*'); @endphp
        <a href="{{ route($route) }}" @class([
            '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
            'border-brand-600 text-brand-700 dark:text-brand-400' => $active,
            'border-transparent text-stone-500 hover:text-stone-800 dark:hover:text-stone-200' => ! $active,
        ]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
