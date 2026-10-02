@props(['action' => null, 'exports' => []])
{{-- Barra de filtros GET. `exports`: [['label' => 'Excel', 'format' => 'xlsx', 'route' => '...'], ...] respeta los filtros actuales. --}}
<form method="GET" action="{{ $action ?? url()->current() }}" {{ $attributes->merge(['class' => 'panel mb-4 p-4']) }} data-allow-resubmit data-filters>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
        {{ $slot }}
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button class="btn btn-primary btn-sm" type="submit"><x-icon name="filter" class="size-4"/> Filtrar</button>
        <a href="{{ $action ?? url()->current() }}" class="btn btn-ghost btn-sm">Limpiar</a>
        <div class="ml-auto flex items-center gap-2">
            <label class="text-xs text-stone-500">Por página</label>
            <select name="per_page" class="form-input w-20 py-1 text-xs" onchange="this.form.submit()">
                @foreach ([10, 25, 50, 100] as $n)
                    <option value="{{ $n }}" @selected((int) request('per_page', 25) === $n)>{{ $n }}</option>
                @endforeach
            </select>
            @foreach ($exports as $export)
                <a href="{{ $export['route'].(str_contains($export['route'], '?') ? '&' : '?').http_build_query(array_merge(request()->except('page'), ['format' => $export['format']])) }}"
                   class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4"/> {{ $export['label'] }}</a>
            @endforeach
        </div>
    </div>
</form>
