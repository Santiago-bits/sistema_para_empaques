{{-- Cadena de trazabilidad + línea de tiempo (QUÉ · QUIÉN · CUÁNDO · DÓNDE · CUÁNTO · POR QUÉ). --}}
<x-panel title="Cadena de trazabilidad" class="mb-6">
    <ol class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
        @foreach ($chain as $node)
            <li @class(['relative rounded-lg border p-3', 'border-brand-500/40 bg-brand-500/5' => $node['value'], 'border-dashed border-stone-300 opacity-60 dark:border-stone-700' => ! $node['value']])>
                <p class="text-[11px] font-semibold tracking-wider text-stone-500 uppercase">{{ $node['label'] }}</p>
                @if ($node['value'])
                    @if ($node['url'])
                        <a href="{{ $node['url'] }}" class="link mt-0.5 block truncate text-sm" title="{{ $node['value'] }}">{{ $node['value'] }}</a>
                    @else
                        <p class="mt-0.5 truncate text-sm font-medium text-stone-900 dark:text-white" title="{{ $node['value'] }}">{{ $node['value'] }}</p>
                    @endif
                    @if ($node['hint'])
                        <p class="truncate text-xs text-stone-500" title="{{ $node['hint'] }}">{{ $node['hint'] }}</p>
                    @endif
                @else
                    <p class="mt-0.5 text-sm text-stone-400">Pendiente</p>
                @endif
            </li>
        @endforeach
    </ol>
</x-panel>

<x-panel title="Historial completo" :padding="false">
    <table class="table">
        <thead><tr><th class="w-36">Cuándo</th><th>Qué</th><th>Quién</th><th>Dónde</th><th class="num">Cuánto</th><th>Por qué / detalle</th></tr></thead>
        <tbody>
            @forelse ($timeline as $event)
                <tr>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($event['time'], true) }}</td>
                    <td>
                        <span class="inline-flex items-center gap-2 font-medium text-stone-900 dark:text-white">
                            <span class="size-2 shrink-0 rounded-full {{ match ($event['color']) { 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'sky' => 'bg-sky-500', 'violet' => 'bg-violet-500', 'stone' => 'bg-stone-400', default => 'bg-brand-500' } }}"></span>
                            {{ $event['title'] }}
                        </span>
                    </td>
                    <td>{{ $event['user'] ?? '—' }}</td>
                    <td class="text-stone-600 dark:text-stone-400">{{ $event['where'] ?? '—' }}</td>
                    <td class="num whitespace-nowrap">{{ $event['amount'] ?? '' }}</td>
                    <td class="text-stone-600 dark:text-stone-400">
                        @if ($event['reason'])<em>{{ $event['reason'] }}</em>@endif
                        @if ($event['reason'] && $event['detail']) · @endif
                        {{ $event['detail'] }}
                    </td>
                </tr>
            @empty
                <x-empty colspan="6" message="Sin eventos registrados."/>
            @endforelse
        </tbody>
    </table>
</x-panel>
