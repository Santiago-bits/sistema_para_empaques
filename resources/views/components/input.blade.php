@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'code' => null])
{{-- code: «crate», «pallet», «lot», «load», «remito» → el prefijo va solo y alcanza con escribir el número. --}}
@php
    $dotName = str_replace(['[', ']'], ['.', ''], preg_replace('/\[\]$/', '', $name));
    $current = old($dotName, $value instanceof \DateTimeInterface ? $value->format($type === 'date' ? 'Y-m-d' : ($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d H:i')) : $value);
@endphp
<x-field :label="$label" :name="$dotName" :hint="$hint" :required="$required">
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $attributes->get('id', $dotName) }}"
           @if ($type !== 'password') value="{{ $current }}" @endif
           @if ($required) required @endif
           @if ($code) {{ code_attrs($code) }} inputmode="numeric" autocomplete="off" @endif
           {{ $attributes->except('id')->class(['form-input', 'is-invalid' => $errors->has($dotName)]) }}>
</x-field>
