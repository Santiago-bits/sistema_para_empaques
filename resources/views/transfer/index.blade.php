<x-layouts.app title="Importar y exportar">
    <x-page-header title="Importar y exportar (Excel)" subtitle="Bajá cualquier listado a Excel, o cargá muchos datos de una sola vez subiendo una planilla."/>

    <div class="mb-6 grid gap-3 md:grid-cols-3">
        <div class="panel p-4">
            <p class="flex items-center gap-2 font-semibold"><x-icon name="download" class="size-5 text-brand-600"/> Exportar = bajar</p>
            <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">Tocá <strong>Bajar Excel</strong> y se descarga la planilla con todos los datos, lista para abrir con Excel.</p>
        </div>
        @if ($canImport)
            <div class="panel p-4">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="upload" class="size-5 text-brand-600"/> Importar = subir</p>
                <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">Bajá la <strong>planilla modelo</strong>, completala en Excel (una fila por cada uno) y tocá <strong>Subir Excel</strong>.</p>
            </div>
            <div class="panel p-4">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="check" class="size-5 text-brand-600"/> Sin miedo a equivocarte</p>
                <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">Antes de guardar te muestra qué va a cargar y qué filas tienen errores. Nada se guarda hasta que confirmes.</p>
            </div>
        @endif
    </div>

    @if ($catalogs->isNotEmpty())
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Fichas: personas, empresas y vehículos</h2>
        <div class="mb-8 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($catalogs as $c)
                <div class="panel flex flex-col p-4">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-600/10 text-brand-700 dark:text-brand-400"><x-icon :name="$c['icon']" class="size-5"/></span>
                        <div class="min-w-0">
                            <a href="{{ $c['list'] }}" class="font-semibold hover:underline">{{ $c['title'] }}</a>
                            <p class="text-xs text-stone-500">{{ num($c['count']) }} {{ $c['count'] === 1 ? 'cargado' : 'cargados' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ $c['excel'] }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4"/> Bajar Excel</a>
                        @if ($c['import'])
                            <a href="{{ $c['import'] }}" class="btn btn-primary btn-sm"><x-icon name="upload" class="size-4"/> Subir Excel</a>
                            <a href="{{ $c['template'] }}" class="btn btn-ghost btn-sm" title="Planilla vacía con las columnas correctas">Planilla modelo</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($movements->isNotEmpty())
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Movimientos (últimos 30 días)</h2>
        <div class="mb-8 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($movements as $m)
                <div class="panel flex items-center justify-between gap-3 p-4">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-500/10 text-sky-700 dark:text-sky-400"><x-icon :name="$m['icon']" class="size-5"/></span>
                        <a href="{{ $m['page'] }}" class="font-semibold hover:underline">{{ $m['title'] }}</a>
                    </span>
                    <a href="{{ $m['excel'] }}" class="btn btn-secondary btn-sm shrink-0"><x-icon name="download" class="size-4"/> Bajar Excel</a>
                </div>
            @endforeach
        </div>
        <p class="text-sm text-stone-500">Para elegir otras fechas o filtrar (por cliente, variedad, etc.) entrá al nombre y usá los filtros: el Excel sale igual que lo que ves.</p>
    @endif

    @if ($canImport)
        <p class="mt-6 text-sm"><a href="{{ route('imports.index') }}" class="link">Ver las planillas subidas antes</a></p>
    @endif
</x-layouts.app>
