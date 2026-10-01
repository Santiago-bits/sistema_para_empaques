@props(['label' => null, 'name', 'value' => null, 'hint' => null, 'required' => false, 'rows' => 3])
<x-field :label="$label" :name="$name" :hint="$hint" :required="$required">
    <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif
              {{ $attributes->class(['form-input', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
</x-field>
