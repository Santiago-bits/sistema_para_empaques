<x-layouts.app title="Usuarios">
    <x-page-header title="Usuarios" subtitle="Cuentas de acceso, roles y estado">
        <x-slot:actions>
            @can('users.manage')
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo usuario</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Nombre, usuario, DNI, código"/>
        <x-select name="role_id" label="Rol" :options="$roles" :value="request('role_id')" placeholder="Todos"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead>
            <tr><th>Nombre</th><th>Usuario</th><th>DNI</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td class="font-medium text-stone-900 dark:text-white">{{ $user->full_name }}</td>
                    <td class="code">{{ $user->username }}</td>
                    <td class="tabular-nums">{{ $user->dni ?? '—' }}</td>
                    <td>{{ $user->role?->name ?? '—' }}</td>
                    <td><x-status :status="$user->status"/> @if ($user->kiosk_mode)<x-badge color="orange">Kiosco</x-badge>@endif</td>
                    <td class="tabular-nums">{{ fdate($user->last_login_at, true) }}</td>
                    <td class="text-right"><a href="{{ route('admin.users.show', $user) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="7"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $users->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
