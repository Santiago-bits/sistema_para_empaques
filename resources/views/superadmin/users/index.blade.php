<x-layouts.app title="Administración general · Usuarios">
    <x-page-header title="Usuarios" subtitle="Todos los datos para poder ayudar a cada uno. Las contraseñas están cifradas y no se pueden ver."/>

    @include('superadmin._nav')

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Nombre, usuario, email, DNI, teléfono"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-select name="role_id" label="Rol" :options="$roles" :value="request('role_id')" placeholder="Todos"/>
        <x-select name="filter" label="Ver" :options="['temporary' => 'Con contraseña temporal', 'never' => 'Nunca ingresaron', 'inactive_30' => 'Sin ingresar hace 30 días']" :value="request('filter')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Teléfono</th><th>DNI</th><th>Rol</th><th>Estado</th><th>Último ingreso</th></tr></thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td><a href="{{ route('superadmin.users.show', $user) }}" class="font-medium text-stone-900 hover:underline dark:text-white">{{ $user->full_name }}</a></td>
                    <td class="code">{{ $user->username }}</td>
                    <td class="text-sm">{{ $user->email ?: '—' }}</td>
                    <td class="text-sm whitespace-nowrap">{{ $user->phone ?: '—' }}</td>
                    <td class="text-sm tabular-nums">{{ $user->dni ?: '—' }}</td>
                    <td class="text-sm">{{ $user->role?->name ?? '—' }}</td>
                    <td>
                        <x-status :status="$user->status"/>
                        @if ($user->must_change_password)
                            <x-badge color="amber">Contraseña temporal</x-badge>
                        @endif
                    </td>
                    <td class="text-sm whitespace-nowrap text-stone-500">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Nunca' }}</td>
                </tr>
            @empty
                <x-empty :colspan="8" message="No hay usuarios con esos filtros."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $users->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
