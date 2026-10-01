@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-stone-500 hover:text-stone-800 dark:hover:text-stone-200">
                <x-icon name="arrow-left" class="size-3.5"/> Volver
            </a>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
