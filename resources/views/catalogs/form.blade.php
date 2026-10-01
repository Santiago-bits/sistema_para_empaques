@php $editing = $record->exists; @endphp
<x-layouts.app :title="$editing ? 'Editar '.$definition->singular() : $definition->newLabel()">
    <x-page-header :title="$editing ? 'Editar: '.$definition->recordLabel($record) : $definition->newLabel()"
                   :back="$editing && $definition->hasShow() ? $definition->route('show', $record->getKey()) : $definition->route('index')"/>

    <form method="POST" action="{{ $editing ? $definition->route('update', $record->getKey()) : $definition->route('store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-panel>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($definition->fields() as $field)
                    @php
                        $attrs = $field->attributes;
                        $wrapClass = $field->wide ? 'md:col-span-2 xl:col-span-3' : '';
                    @endphp
                    <div class="{{ $wrapClass }}">
                        @switch($field->type)
                            @case('select')
                                <x-select :name="$field->name" :label="$field->label" :options="$field->options()" :value="$field->value($record)"
                                          :placeholder="$field->placeholder ?? ($field->required ? 'Seleccionar…' : '—')" :required="$field->required" :hint="$field->hint"
                                          :attributes="new \Illuminate\View\ComponentAttributeBag($attrs)"/>
                                @break
                            @case('textarea')
                                <x-textarea :name="$field->name" :label="$field->label" :value="$field->value($record)" :required="$field->required" :hint="$field->hint"/>
                                @break
                            @case('checkbox')
                                <div class="pt-6">
                                    <x-checkbox :name="$field->name" :label="$field->label" :checked="(bool) $field->value($record)" :hint="$field->hint"/>
                                </div>
                                @break
                            @default
                                <x-input :name="$field->name" :type="$field->type" :label="$field->label" :value="$field->value($record)"
                                         :required="$field->required" :hint="$field->hint" :placeholder="$field->placeholder"
                                         :attributes="new \Illuminate\View\ComponentAttributeBag($attrs)"/>
                        @endswitch
                    </div>
                @endforeach
            </div>
        </x-panel>

        <div class="flex items-center justify-between gap-2">
            <div>
                @if ($editing && $definition->canDelete())
                    <button type="submit" form="delete-form" class="btn btn-ghost text-red-600">Eliminar</button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ $definition->route('index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    </form>

    @if ($editing && $definition->canDelete())
        <form id="delete-form" method="POST" action="{{ $definition->route('destroy', $record->getKey()) }}" x-data
              x-confirm="¿Eliminar {{ $definition->recordLabel($record) }}? Si tiene registros asociados no se podrá eliminar: desactivalo.">
            @csrf @method('DELETE')
        </form>
    @endif
</x-layouts.app>
