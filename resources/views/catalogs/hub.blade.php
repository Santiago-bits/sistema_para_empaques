<x-layouts.app title="Catálogos">
    <x-page-header title="Catálogos" subtitle="Datos maestros del galpón: personas, empresas, productos y parámetros de producción.">
        <x-slot:actions>
            @can('lots.view')
                <a href="{{ route('lots.index') }}" class="btn btn-secondary"><x-icon name="layers" class="size-4"/> Lotes</a>
            @endcan
            @can('imports.manage')
                <a href="{{ route('imports.index') }}" class="btn btn-secondary"><x-icon name="upload" class="size-4"/> Importar datos</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-8">
        @foreach ($groups as $group => $definitions)
            <section>
                <h2 class="mb-3 text-xs font-semibold tracking-wider text-stone-500 uppercase">{{ $group }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($definitions as $definition)
                        <a href="{{ $definition->route('index') }}" class="panel group flex items-start gap-3 p-4 transition hover:border-brand-400 hover:shadow">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-600/10 text-brand-700 dark:text-brand-400">
                                <x-icon :name="$definition->icon()" class="size-5"/>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span class="font-medium text-stone-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">{{ $definition->title() }}</span>
                                    <span class="text-lg font-semibold tabular-nums text-stone-400">{{ num($definition->count()) }}</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-stone-500">{{ $definition->description() }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
