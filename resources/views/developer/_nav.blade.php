{{-- Pestañas del panel de desarrollador. --}}
<nav class="tabs" aria-label="Panel de desarrollador">
    @foreach (['developer.index' => 'Estado', 'developer.errors' => 'Errores', 'developer.logs' => 'Logs', 'developer.licenses.index' => 'Licencias'] as $route => $label)
        @php $active = request()->routeIs($route) || request()->routeIs(str_replace('.index', '', $route).'.*'); @endphp
        <a href="{{ route($route) }}" @class(['tab', 'active' => $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
