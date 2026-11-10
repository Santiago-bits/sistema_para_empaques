<x-layouts.app title="Administración general · Usuarios">
    <x-page-header title="Usuarios" subtitle="Elegí un galpón para ver sus usuarios. Las contraseñas están cifradas y no se pueden ver."/>

    @include('superadmin._nav')

    <form method="GET" action="{{ route('superadmin.users.index') }}" class="mb-6 flex max-w-xl gap-2" role="search">
        <input type="search" name="q" class="form-input" placeholder="Buscar en todos los galpones: nombre, usuario, email, DNI, teléfono" aria-label="Buscar usuario">
        <button class="btn btn-secondary"><x-icon name="search" class="size-4"/> Buscar</button>
    </form>

    @php $super = $counts['super'] ?? null; @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($warehouses as $w)
            @php $c = $counts[$w->id] ?? null; @endphp
            <a href="{{ route('superadmin.users.index', ['warehouse' => $w->id]) }}" data-warehouse-card
               class="panel group flex flex-col gap-4 p-5 transition hover:border-brand-500 hover:shadow-md {{ $w->active ? '' : 'opacity-70' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid size-11 shrink-0 place-items-center bg-brand-600/10 text-brand-700 dark:text-brand-400"><x-icon name="home" class="size-5"/></span>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-semibold text-stone-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">{{ $w->name }}</p>
                            <p class="code text-xs text-stone-500">{{ $w->code }}</p>
                        </div>
                    </div>
                    <x-badge :color="$w->active ? 'emerald' : 'red'">{{ $w->active ? 'Activo' : 'Suspendido' }}</x-badge>
                </div>
                <dl class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-stone-50 p-2 dark:bg-stone-900/60">
                        <dt class="text-xs text-stone-500">Usuarios</dt>
                        <dd class="text-xl font-semibold tabular-nums">{{ num($c->total ?? 0) }}</dd>
                    </div>
                    <div class="bg-stone-50 p-2 dark:bg-stone-900/60">
                        <dt class="text-xs text-stone-500">Clave temporal</dt>
                        <dd class="text-xl font-semibold tabular-nums {{ ($c->temporary ?? 0) ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ num($c->temporary ?? 0) }}</dd>
                    </div>
                    <div class="bg-stone-50 p-2 dark:bg-stone-900/60">
                        <dt class="text-xs text-stone-500">Nunca entraron</dt>
                        <dd class="text-xl font-semibold tabular-nums">{{ num($c->never ?? 0) }}</dd>
                    </div>
                </dl>
                <span class="text-sm font-medium text-brand-700 dark:text-brand-400">Ver usuarios →</span>
            </a>
        @endforeach

        <a href="{{ route('superadmin.users.index', ['warehouse' => 'super']) }}" data-warehouse-card
           class="panel group flex flex-col gap-4 border-dashed p-5 transition hover:border-brand-500 hover:shadow-md">
            <div class="flex items-center gap-3">
                <span class="grid size-11 shrink-0 place-items-center bg-violet-500/10 text-violet-700 dark:text-violet-400"><x-icon name="shield" class="size-5"/></span>
                <div>
                    <p class="text-lg font-semibold text-stone-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">Super Administradores</p>
                    <p class="text-xs text-stone-500">No son de ningún galpón: ven y manejan todo el sistema.</p>
                </div>
            </div>
            <p class="text-sm"><span class="text-xl font-semibold tabular-nums">{{ num($super->total ?? 0) }}</span> usuario(s)</p>
            <span class="text-sm font-medium text-brand-700 dark:text-brand-400">Ver usuarios →</span>
        </a>
    </div>

    @if ($warehouses->isEmpty())
        <p class="mt-6 text-sm text-stone-500">Todavía no hay galpones. Crealos en la pestaña <a href="{{ route('superadmin.warehouses.index') }}" class="link">Galpones</a>.</p>
    @endif
</x-layouts.app>
