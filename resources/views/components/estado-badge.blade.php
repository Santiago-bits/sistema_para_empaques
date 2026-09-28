@props(['estado'])

<span {{ $attributes->merge(['class' => 'badge text-bg-'.$estado->color()]) }}>{{ $estado->label() }}</span>
