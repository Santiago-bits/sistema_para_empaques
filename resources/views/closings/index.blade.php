<x-layouts.app title="Cierre diario">
    <x-page-header title="Cierre diario" subtitle="Resumen congelado de cada jornada. Reabrir un cierre queda auditado con su motivo.">
        <x-slot:actions>
            <a href="{{ route('closings.create') }}" class="btn btn-primary"><x-icon name="lock" class="size-4"/> {{ $todayClosed ? 'Cerrar otro día' : 'Cerrar el día de hoy' }}</a>
        </x-slot:actions>
    </x-page-header>

    <x-table>
        <thead><tr><th>Fecha</th><th>Estado</th><th class="num">Cajones</th><th class="num">Kg procesados</th><th class="num">Cargas</th><th>Cerrado por</th><th></th></tr></thead>
        <tbody>
            @forelse ($closings as $closing)
                <tr>
                    <td class="font-medium text-stone-900 dark:text-white">{{ $closing->date->format('d/m/Y') }}</td>
                    <td>@if ($closing->reopened_at)<x-badge color="amber">Reabierto</x-badge>@else<x-badge color="emerald">Cerrado</x-badge>@endif</td>
                    <td class="num">{{ num($closing->snapshot['crates_processed'] ?? 0) }}</td>
                    <td class="num">{{ kg($closing->snapshot['kg_processed'] ?? 0, 0) }}</td>
                    <td class="num">{{ num($closing->snapshot['loads_dispatched'] ?? 0) }}</td>
                    <td>{{ $closing->closer?->full_name ?? '—' }} <span class="text-xs text-stone-500">{{ fdate($closing->closed_at, true) }}</span></td>
                    <td class="text-right"><a href="{{ route('closings.show', $closing) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="7" message="Todavía no se cerró ningún día."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $closings->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
