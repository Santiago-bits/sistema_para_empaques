<x-layouts.app :title="$association->exists ? 'Editar asociación' : 'Nueva asociación'">
    <x-page-header :title="$association->exists ? 'Editar: '.$association->name : 'Nueva asociación de citricultores'" :back="route('superadmin.associations.index')"/>

    <form method="POST" action="{{ $association->exists ? route('superadmin.associations.update', $association) : route('superadmin.associations.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($association->exists) @method('PUT') @endif
        <x-panel title="Asociación">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2"><x-input name="name" label="Nombre completo" :value="$association->name" required hint="Tal como sale impreso, p. ej. «Asociación de Citricultores de Chajarí»."/></div>
                <x-input name="short_name" label="Nombre corto" :value="$association->short_name" hint="Opcional, p. ej. «Chajarí»."/>
                <x-input name="cuit" label="CUIT" :value="$association->cuit" hint="Opcional, 11 dígitos."/>
                <x-input name="locality" label="Localidad" :value="$association->locality"/>
                <x-input name="province" label="Provincia" :value="$association->province"/>
            </div>
            <div class="mt-4">
                <input type="hidden" name="active" value="0">
                <x-checkbox name="active" label="Activa (las inactivas no salen impresas)" :checked="$association->active"/>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ route('superadmin.associations.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
