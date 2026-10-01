@props(['value' => 0, 'max' => 100, 'label' => null])
@php $pct = $max > 0 ? min(100, max(0, $value / $max * 100)) : 0; @endphp
<div {{ $attributes }}>
    @if ($label)
        <div class="mb-1 flex justify-between text-xs text-stone-500"><span>{{ $label }}</span><span class="tabular-nums">{{ number_format($pct, 0) }} %</span></div>
    @endif
    <div class="h-2.5 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800">
        <div class="h-full rounded-full {{ $pct >= 100 ? 'bg-brand-500' : ($pct >= 60 ? 'bg-brand-600' : 'bg-accent-500') }} transition-all" style="width: {{ $pct }}%"></div>
    </div>
</div>
