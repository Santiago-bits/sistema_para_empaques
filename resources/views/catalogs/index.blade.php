@php
    $canManage = auth()->user()->can($definition->managePermission());
    $bulk = $definition->bulkAction();
@endphp
<x-layouts.app :title="$definition->title()">
    <x-page-header :title="$definition->title()" :subtitle="$definition->description()" :back="route('catalogs.index')">
        <x-slot:actions>
            @foreach ($definition->headerActions() as $action)
                @can($action['permission'])
                    <a href="{{ $action['url'] }}" class="btn btn-secondary"><x-icon :name="$action['icon']" class="size-4"/> {{ $action['label'] }}</a>
                @endcan
            @endforeach
            @if ($canManage)
                <a href="{{ $definition->route('create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> {{ $definition->newLabel() }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Buscar…"/>
        @foreach ($definition->filters() as $filter)
            <x-select :name="$filter->name" :label="$filter->label" :options="$filter->options()" :value="request($filter->name)" placeholder="Todos"/>
        @endforeach
    </x-filters>

    <form method="GET" action="{{ $bulk ? route($bulk['route']) : '#' }}" target="_blank" x-data="{ selected: [] }">
        @if ($bulk)
            <div class="mb-2 flex items-center gap-2" x-show="selected.length > 0" x-cloak>
                <button class="btn btn-secondary btn-sm" type="submit"><x-icon :name="$bulk['icon']" class="size-4"/> {{ $bulk['label'] }} (<span x-text="selected.length"></span>)</button>
            </div>
        @endif
        <x-table>
            <thead>
                <tr>
                    @if ($bulk)<th class="w-8"></th>@endif
                    @foreach ($definition->columns() as $column)
                        <th>{{ $column->label }}</th>
                    @endforeach
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        @if ($bulk)
                            <td><input type="checkbox" name="ids[]" value="{{ $record->getKey() }}" x-model="selected" class="size-4 rounded border-stone-300 text-brand-600" aria-label="Seleccionar"></td>
                        @endif
                        @foreach ($definition->columns() as $column)
                            <td @class(['num' => $column->type === 'num'])>@include('catalogs.partials.cell', ['column' => $column, 'record' => $record])</td>
                        @endforeach
                        <td class="text-right whitespace-nowrap">
                            @if ($definition->hasShow())
                                <a href="{{ $definition->route('show', $record->getKey()) }}" class="link">Ver</a>
                            @endif
                            @if ($canManage)
                                <a href="{{ $definition->route('edit', $record->getKey()) }}" class="link ml-3">Editar</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-empty :colspan="count($definition->columns()) + 2"/>
                @endforelse
            </tbody>
            <x-slot:footer>{{ $records->links() }}</x-slot:footer>
        </x-table>
    </form>
</x-layouts.app>
