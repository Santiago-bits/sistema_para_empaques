{{-- Celda de listado según el tipo de Column (ver app/Catalogs/Column.php). --}}
@php $value = $column->resolve($record); @endphp
@switch($column->type)
    @case('strong')
        <span class="font-medium text-stone-900 dark:text-white">{{ $value ?? '—' }}</span>
        @break
    @case('code')
        <span class="code">{{ $value ?? '—' }}</span>
        @break
    @case('num')
        <span class="tabular-nums">{{ $value === null ? '—' : $value }}</span>
        @break
    @case('active')
        <x-active-badge :active="(bool) $value"/>
        @break
    @case('color')
        @if ($value)
            <span class="inline-block size-4 rounded-full ring-1 ring-black/10" style="background-color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#999999' }}"></span>
        @endif
        @break
    @case('date')
        <span class="tabular-nums">{{ $value instanceof \DateTimeInterface ? fdate($value) : ($value ?? '—') }}</span>
        @break
    @case('license')
        @if ($value instanceof \DateTimeInterface)
            @php $days = (int) floor(now()->startOfDay()->diffInDays($value->copy()->startOfDay(), false)); @endphp
            <span class="tabular-nums">{{ fdate($value) }}</span>
            @if ($days < 0)
                <x-badge color="red">Vencida</x-badge>
            @elseif ($days <= 15)
                <x-badge color="amber">Vence en {{ $days }} d</x-badge>
            @endif
        @else
            —
        @endif
        @break
    @case('badge')
        @if (is_array($value))
            <x-badge :color="$value[1] ?? 'stone'">{{ $value[0] }}</x-badge>
        @endif
        @break
    @default
        {{ ($value === null || $value === '') ? '—' : $value }}
@endswitch
