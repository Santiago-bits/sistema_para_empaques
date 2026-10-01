{{-- Nodo recursivo del árbol de ubicaciones. --}}
@php
    $stats = $occupancy[$node->id] ?? ['total' => 0, 'capacity' => 0, 'pct' => 0, 'crates' => 0];
    $children = $tree->get($node->id, collect());
    $highlight = $matches && $matches->contains($node->id);
@endphp
<li x-data="{ open: {{ $depth < 1 || $matches ? 'true' : 'false' }} }">
    <div @class(['group flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800/60', 'bg-amber-100/60 dark:bg-amber-500/10' => $highlight])>
        @if ($children->isNotEmpty())
            <button type="button" @click="open = !open" class="grid size-5 place-items-center text-stone-400" :aria-expanded="open" aria-label="Expandir">
                <x-icon name="chevron-right" class="size-4 transition" ::class="open && 'rotate-90'"/>
            </button>
        @else
            <span class="size-5"></span>
        @endif
        <a href="{{ route('locations.show', $node) }}" class="flex min-w-0 flex-1 items-center gap-2">
            <span class="code text-xs text-stone-500">{{ $node->code }}</span>
            <span @class(['truncate font-medium text-stone-900 dark:text-white', 'line-through opacity-50' => ! $node->active])>{{ $node->name }}</span>
            <x-badge class="hidden sm:inline-flex">{{ $types[$node->type] ?? $node->type }}</x-badge>
        </a>
        <span class="hidden w-40 sm:block">
            @if ($stats['capacity'] > 0)
                <x-progress :value="$stats['total']" :max="$stats['capacity']"/>
            @endif
        </span>
        <span class="w-24 text-right text-xs tabular-nums text-stone-500">{{ $stats['total'] }}{{ $stats['capacity'] ? ' / '.$stats['capacity'] : '' }} pallets</span>
    </div>
    @if ($children->isNotEmpty())
        <ul x-show="open" class="ml-5 border-l border-stone-200 pl-2 dark:border-stone-800">
            @foreach ($children as $child)
                @include('locations._node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
