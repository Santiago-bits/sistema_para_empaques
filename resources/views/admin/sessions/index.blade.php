<x-layouts.app title="Sesiones activas">
    <x-page-header title="Sesiones activas" subtitle="Usuarios con actividad en las últimas 4 horas."/>

    <x-table>
        <thead><tr><th>Usuario</th><th>Rol</th><th>IP</th><th>Navegador</th><th>Última actividad</th><th></th></tr></thead>
        <tbody>
            @forelse ($sessions as $session)
                <tr>
                    <td class="font-medium">
                        {{ $session->user->full_name }}
                        @if ($session->id === session()->getId())<x-badge color="emerald">Esta sesión</x-badge>@endif
                    </td>
                    <td>{{ $session->user->role?->name }}</td>
                    <td class="code">{{ $session->ip_address }}</td>
                    <td class="max-w-xs truncate text-xs text-stone-500" title="{{ $session->user_agent }}">{{ $session->user_agent }}</td>
                    <td class="tabular-nums">{{ $session->last_activity->diffForHumans() }}</td>
                    <td class="text-right">
                        @if ($session->id !== session()->getId())
                            <form method="POST" action="{{ route('admin.sessions.destroy', $session->id) }}" x-data x-confirm="¿Cerrar la sesión de {{ $session->user->full_name }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-secondary btn-sm text-red-600">Cerrar sesión</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="6" message="No hay sesiones activas."/>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
