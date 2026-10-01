@props(['events' => []])
{{-- events: lista de ['time' => Carbon, 'title' => string, 'detail' => ?string, 'user' => ?string, 'color' => ?string] --}}
<ol class="relative ml-2 border-l border-stone-200 dark:border-stone-800">
    @forelse ($events as $event)
        <li class="mb-5 ml-5">
            <span class="absolute -left-[7px] mt-1.5 size-3 rounded-full ring-4 ring-white dark:ring-stone-900 {{ match ($event['color'] ?? 'brand') { 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'sky' => 'bg-sky-500', 'violet' => 'bg-violet-500', 'stone' => 'bg-stone-400', default => 'bg-brand-500' } }}"></span>
            <time class="text-xs tabular-nums text-stone-500">{{ fdate($event['time'], true) }}</time>
            <p class="text-sm font-medium text-stone-900 dark:text-stone-100">{{ $event['title'] }}</p>
            @if (! empty($event['detail']))
                <p class="text-sm text-stone-600 dark:text-stone-400">{{ $event['detail'] }}</p>
            @endif
            @if (! empty($event['user']))
                <p class="text-xs text-stone-500">por {{ $event['user'] }}</p>
            @endif
        </li>
    @empty
        <li class="ml-5 text-sm text-stone-500">Sin eventos registrados.</li>
    @endforelse
</ol>
