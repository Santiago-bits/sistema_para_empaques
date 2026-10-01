<x-layouts.app :title="$role->exists ? 'Editar rol' : 'Nuevo rol'">
    <x-page-header :title="$role->exists ? 'Rol: '.$role->name : 'Nuevo rol'" :back="route('admin.roles.index')"/>

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="space-y-6"
          x-data="{ toggleGroup(group, value) { document.querySelectorAll('[data-group=' + group + ']').forEach(c => c.checked = value) } }">
        @csrf
        @if ($role->exists) @method('PUT') @endif

        <x-panel>
            <div class="grid gap-4 md:grid-cols-2">
                <x-input name="name" label="Nombre" :value="$role->name" required/>
                <x-input name="description" label="Descripción" :value="$role->description"/>
            </div>
        </x-panel>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($permissions as $module => $items)
                <x-panel :title="$moduleNames[$module] ?? $module">
                    <x-slot:actions>
                        <button type="button" class="text-xs link" @click="toggleGroup('{{ $module }}', true)">Todos</button>
                        <button type="button" class="text-xs text-stone-500 hover:underline" @click="toggleGroup('{{ $module }}', false)">Ninguno</button>
                    </x-slot:actions>
                    <div class="space-y-2">
                        @foreach ($items as $permission)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->slug }}" data-group="{{ $module }}"
                                       class="size-4 rounded border-stone-300 text-brand-600 dark:border-stone-600 dark:bg-stone-900"
                                       @checked(in_array($permission->slug, $selected, true))>
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </div>
                </x-panel>
            @endforeach
        </div>

        <div class="sticky bottom-4 flex justify-end">
            <button class="btn btn-primary shadow-lg">Guardar rol</button>
        </div>
    </form>
</x-layouts.app>
