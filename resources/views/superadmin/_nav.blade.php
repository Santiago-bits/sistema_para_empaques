{{-- Secciones de la administración general. --}}
@php
    $central = (bool) config('galpon.central.mode');
    $openTickets = $central ? \App\Models\ClientTicket::query()->whereNotIn('status', ['resolved', 'closed'])->count() : 0;
    $tabs = [
        ['superadmin.index', 'Resumen', 'chart-bar', 'superadmin.index', null],
        ['superadmin.users.index', 'Usuarios', 'users', 'superadmin.users.*', null],
    ];
    if ($central) {
        $tabs[] = ['superadmin.clients.index', 'Clientes y pagos', 'briefcase', 'superadmin.clients.*', null];
        $tabs[] = ['central.tickets.index', 'Soporte', 'lifebuoy', 'central.tickets.*', $openTickets];
    }
@endphp
<div class="mb-6 flex flex-wrap items-end justify-between gap-3 border-b border-stone-200 dark:border-stone-800">
    <nav class="-mb-px flex flex-wrap gap-1" aria-label="Administración general">
        @foreach ($tabs as [$route, $label, $icon, $pattern, $badge])
            <a href="{{ route($route) }}" @class([
                'flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                'border-brand-600 font-medium text-brand-700 dark:text-brand-400' => request()->routeIs($pattern),
                'border-transparent text-stone-500 hover:text-stone-800 dark:hover:text-stone-200' => ! request()->routeIs($pattern),
            ]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                <x-icon :name="$icon" class="size-4"/> {{ $label }}
                @if ($badge)
                    <span class="rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white tabular-nums">{{ $badge }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('superadmin.lock') }}" class="pb-2">
        @csrf
        <button class="btn btn-ghost btn-sm" title="Vuelve a pedir tu contraseña la próxima vez"><x-icon name="lock" class="size-4"/> Salir de la administración</button>
    </form>
</div>
