<x-layouts.app title="Administración general · Galpones">
    <x-page-header title="Galpones" subtitle="Un solo sistema para todos tus galpones. Cada uno con su administrador, sus empleados y sus propios datos: nunca se mezclan.">
        <x-slot:actions>
            <a href="{{ route('superadmin.warehouses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo galpón</a>
        </x-slot:actions>
    </x-page-header>

    @include('superadmin._nav')

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Nombre o código"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Galpón</th><th>Administrador general</th><th class="num">Usuarios</th><th class="num">Cajones 30 d</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @forelse ($warehouses as $w)
                <tr>
                    <td>
                        <a href="{{ route('superadmin.warehouses.show', $w) }}" class="font-medium text-stone-900 hover:underline dark:text-white">{{ $w->name }}</a>
                        <span class="code block text-xs text-stone-500">{{ $w->code }}</span>
                    </td>
                    <td class="text-sm">
                        @forelse ($admins[$w->id] ?? [] as $admin)
                            <span class="code block">{{ $admin->username }}</span>
                        @empty
                            <x-badge color="amber">Falta crearlo</x-badge>
                        @endforelse
                    </td>
                    <td class="num">{{ num($users[$w->id] ?? 0) }}</td>
                    <td class="num">{{ num($crates[$w->id] ?? 0) }}</td>
                    <td>
                        <x-badge :color="$w->active ? 'emerald' : 'red'">{{ $w->active ? 'Activo' : 'Suspendido' }}</x-badge>
                        @if ($current === $w->id)<x-badge color="sky">Estás acá</x-badge>@endif
                    </td>
                    <td class="text-right">
                        @if ($current !== $w->id)
                            <form method="POST" action="{{ route('warehouses.enter', $w) }}">
                                @csrf
                                <button class="btn btn-secondary btn-sm">Entrar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="6" message="Todavía no hay galpones. Tocá «Nuevo galpón»."/>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
