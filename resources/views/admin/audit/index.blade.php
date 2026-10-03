<x-layouts.app title="Historial de cambios">
    <x-page-header title="Historial de cambios" subtitle="Registro inmutable de operaciones: qué, quién, cuándo, desde dónde y por qué."/>

    <x-filters>
        <x-select name="user_id" label="Usuario" :options="$users" :value="request('user_id')" placeholder="Todos"/>
        <x-select name="action" label="Acción" :options="$actions->map(fn ($a) => __('audit.actions.'.$a))" :value="request('action')" placeholder="Todas"/>
        <x-select name="type" label="Entidad" :options="$types->map(fn ($t) => __('entities.'.$t))" :value="request('type')" placeholder="Todas"/>
        <x-input name="id" label="ID entidad" :value="request('id')" inputmode="numeric"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th><th></th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($log->created_at, true) }}</td>
                    <td>{{ $log->user?->full_name ?? 'Sistema' }}</td>
                    <td><x-badge :color="match (true) { str_contains($log->action, 'delete') || str_contains($log->action, 'void') || $log->action === 'login_failed' => 'red', $log->action === 'create' => 'emerald', $log->action === 'update' => 'blue', default => 'stone' }">{{ __('audit.actions.'.$log->action) }}</x-badge></td>
                    <td class="whitespace-nowrap">{{ $log->auditable_type ? __('entities.'.$log->auditable_type) : '—' }}{{ $log->auditable_id ? ' #'.$log->auditable_id : '' }}</td>
                    <td class="max-w-md truncate text-stone-500">
                        {{ $log->description }}
                        @if ($log->action === 'update' && $log->new_values)
                            {{ collect($log->new_values)->keys()->take(4)->map(fn ($k) => field_label($k))->join(', ') }}
                        @endif
                        @if ($log->reason) · <em>{{ $log->reason }}</em> @endif
                    </td>
                    <td class="code text-xs">{{ $log->ip_address }}</td>
                    <td class="text-right"><a href="{{ route('admin.audit.show', $log) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="7"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
