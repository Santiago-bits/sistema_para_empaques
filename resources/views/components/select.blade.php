@props(['label' => null, 'name', 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false])
{{-- options: [valor => etiqueta]. El valor seleccionado respeta old(). --}}
@php
    $dotName = str_replace(['[', ']'], ['.', ''], preg_replace('/\[\]$/', '', $name));
    $selected = old($dotName, $value instanceof \BackedEnum ? $value->value : $value);
    $isMultiple = $attributes->has('multiple');
@endphp
<x-field :label="$label" :name="$dotName" :hint="$hint" :required="$required">
    <select name="{{ $name }}" id="{{ $attributes->get('id', $dotName) }}" @if ($required) required @endif
            {{ $attributes->except('id')->class(['form-input', 'is-invalid' => $errors->has($dotName)]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($isMultiple ? in_array((string) $optValue, array_map('strval', (array) $selected), true) : (string) $optValue === (string) $selected)>{{ $optLabel }}</option>
        @endforeach
    </select>
</x-field>
