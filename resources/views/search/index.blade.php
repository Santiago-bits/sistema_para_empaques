<x-layouts.app title="Buscar">
    <x-page-header title="Buscar" :subtitle="$term !== '' ? 'Resultados para «'.$term.'»' : 'Cajón, pallet, lote, carga, remito, factura, CUIT, patente, chofer o embalador'"/>

    <form method="GET" action="{{ route('search') }}" class="panel mb-6 flex gap-3 p-4" role="search" data-allow-resubmit>
        <input type="search" name="q" value="{{ $term }}" class="form-input flex-1 py-2.5 text-base" placeholder="Escribí o escaneá un código…" autofocus maxlength="100" aria-label="Buscar">
        <button class="btn btn-primary"><x-icon name="search" class="size-4"/> Buscar</button>
    </form>

    @if ($tooShort)
        <p class="text-sm text-stone-500">Escribí al menos 2 caracteres.</p>
    @elseif ($term !== '' && ! $groups)
        <div class="panel p-10 text-center">
            <x-icon name="search" class="mx-auto mb-2 size-8 text-stone-300 dark:text-stone-600"/>
            <p class="text-sm text-stone-500">No encontramos nada con «{{ $term }}».</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($groups as $group)
            <x-panel :title="$group['label']" :padding="false">
                <ul class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50 dark:hover:bg-stone-800/60">
                                <x-icon :name="$group['icon']" class="size-4 shrink-0 text-stone-400"/>
                                <span class="min-w-0 flex-1">
                                    <span @class(['block truncate font-medium text-stone-900 dark:text-white', 'code' => in_array($group['key'], ['crates', 'pallets', 'lots', 'loads', 'remitos', 'invoices', 'trucks'], true)])>{{ $item['title'] }}</span>
                                    @if ($item['subtitle'])<span class="block truncate text-xs text-stone-500">{{ $item['subtitle'] }}</span>@endif
                                </span>
                                <x-icon name="chevron-right" class="size-4 text-stone-400"/>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endforeach
    </div>
</x-layouts.app>
