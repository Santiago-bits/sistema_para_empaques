@props(['active'])
@if ($active)
    <x-badge color="emerald">Activo</x-badge>
@else
    <x-badge color="zinc">Inactivo</x-badge>
@endif
