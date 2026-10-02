@php
    $severityColors = ['critical' => 'red', 'warning' => 'amber', 'info' => 'sky'];
    $severities = \App\Services\AlertService::SEVERITIES;
@endphp
<x-layouts.app title="Alertas">
    <x-page-header title="Alertas" subtitle="Se evalúan solas cada 5 minutos y se resuelven cuando la condición desaparece.">
        <x-slot:actions>
            @can('alerts.manage')
                <form method="POST" action="{{ route('alerts.check') }}">
                    @csrf
                    <button class="btn btn-secondary"><x-icon name="refresh" class="size-4"/> Evaluar ahora</button>
                </form>
                @if (Route::has('admin.settings.index'))
                    <a href="{{ route('admin.settings.index', ['tab' => 'alerts']) }}" class="btn btn-ghost"><x-icon name="cog" class="size-4"/> Configurar</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-3 gap-3">
        <x-stat label="Críticas" :value="num($counts['critical'] ?? 0)" icon="alert" color="red" :href="route('alerts.index', ['severity' => 'critical'])"/>
        <x-stat label="Advertencias" :value="num($counts['warning'] ?? 0)" icon="alert" color="amber" :href="route('alerts.index', ['severity' => 'warning'])"/>
        <x-stat label="Informativas" :value="num($counts['info'] ?? 0)" icon="info" color="sky" :href="route('alerts.index', ['severity' => 'info'])"/>
    </div>

    <x-filters>
        <x-select name="status" label="Estado" :options="['open' => 'Abiertas', 'resolved' => 'Resueltas', 'all' => 'Todas']" :value="$status"/>
        <x-select name="severity" label="Severidad" :options="$severities" :value="request('severity')" placeholder="Todas"/>
        <x-select name="type" label="Tipo" :options="$types" :value="request('type')" placeholder="Todos"/>
    </x-filters>

    <div class="panel divide-y divide-stone-200 dark:divide-stone-800">
        @forelse ($alerts as $alert)
            <div class="flex items-start gap-4 p-4">
                <x-badge :color="$severityColors[$alert->severity] ?? 'stone'">{{ $severities[$alert->severity] ?? $alert->severity }}</x-badge>
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-stone-900 dark:text-white">{{ $alert->title }}</p>
                    @if ($alert->message)<p class="mt-0.5 text-sm text-stone-600 dark:text-stone-300">{{ $alert->message }}</p>@endif
                    <p class="mt-1 text-xs text-stone-500">
                        {{ $types[$alert->type] ?? $alert->type }} · desde {{ fdate($alert->created_at, true) }}
                        @if ($alert->resolved_at) · resuelta {{ fdate($alert->resolved_at, true) }} {{ $alert->resolver ? 'por '.$alert->resolver->full_name : '(automáticamente)' }}@endif
                    </p>
                </div>
                @if (! $alert->resolved_at)
                    @can('alerts.manage')
                        <form method="POST" action="{{ route('alerts.resolve', $alert) }}">
                            @csrf
                            <button class="btn btn-secondary btn-sm"><x-icon name="check" class="size-4"/> Resolver</button>
                        </form>
                    @endcan
                @endif
            </div>
        @empty
            <p class="p-10 text-center text-sm text-stone-500">{{ $status === 'open' ? 'No hay alertas abiertas. Todo en orden.' : 'No hay alertas para mostrar.' }}</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $alerts->links() }}</div>
</x-layouts.app>
