@props(['status'])
{{-- Badge para cualquier enum de estado con label() y color(). --}}
@if ($status instanceof \BackedEnum && method_exists($status, 'label'))
    <x-badge :color="$status->color()" {{ $attributes }}>{{ $status->label() }}</x-badge>
@elseif ($status)
    <x-badge {{ $attributes }}>{{ $status }}</x-badge>
@endif
