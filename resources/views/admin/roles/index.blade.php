<x-layouts.app title="Roles y permisos">
    <x-page-header title="Roles y permisos" subtitle="Cada rol agrupa permisos granulares. También se pueden ajustar permisos por usuario.">
        <x-slot:actions>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo rol</a>
        </x-slot:actions>
    </x-page-header>

    <x-table>
        <thead><tr><th>Rol</th><th>Descripción</th><th class="num">Usuarios</th><th class="num">Permisos</th><th></th></tr></thead>
        <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td class="font-medium text-stone-900 dark:text-white">
                        {{ $role->name }}
                        @if ($role->is_system)<x-badge class="ml-1">Sistema</x-badge>@endif
                    </td>
                    <td class="text-stone-500">{{ $role->description }}</td>
                    <td class="num">{{ $role->users_count }}</td>
                    <td class="num">{{ $role->slug === 'super_admin' ? 'Todos' : $role->permissions_count }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($role->slug !== 'super_admin')
                            <a href="{{ route('admin.roles.edit', $role) }}" class="link">Editar</a>
                        @endif
                        @if (! $role->is_system && $role->users_count === 0)
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline" x-data x-confirm="¿Eliminar el rol {{ $role->name }}?">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-sm text-red-600 hover:underline">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
</x-layouts.app>
