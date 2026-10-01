@props(['label', 'value', 'icon' => null, 'hint' => null, 'color' => 'brand', 'href' => null, 'delta' => null])
@php
    $colors = [
        'brand' => 'bg-brand-600/10 text-brand-700 dark:text-brand-400',
        'accent' => 'bg-accent-500/10 text-accent-600 dark:text-accent-400',
        'sky' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
        'violet' => 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        'red' => 'bg-red-500/10 text-red-700 dark:text-red-400',
        'amber' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        'stone' => 'bg-stone-500/10 text-stone-700 dark:text-stone-300',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'panel flex items-start gap-3 p-4'.($href ? ' transition hover:border-brand-400 hover:shadow' : '')]) }}>
    @if ($icon)
        <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $colors[$color] ?? $colors['brand'] }}">
            <x-icon :name="$icon" class="size-5"/>
        </span>
    @endif
    <div class="min-w-0">
        <p class="truncate text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">{{ $label }}</p>
        <p class="mt-0.5 text-2xl font-semibold tabular-nums text-stone-900 dark:text-white">{{ $value }}</p>
        @if ($delta !== null)
            @php $d = (float) $delta; @endphp
            <p @class(['text-xs font-medium tabular-nums', 'text-emerald-600 dark:text-emerald-400' => $d >= 0, 'text-red-600 dark:text-red-400' => $d < 0])>
                {{ $d >= 0 ? '▲' : '▼' }} {{ number_format(abs($d), 1, ',', '.') }} % {{ $hint }}
            </p>
        @elseif ($hint)
            <p class="text-xs text-stone-500 dark:text-stone-400">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
