<x-layouts.app title="Administración general · Asociaciones">
    <x-page-header title="Asociaciones de citricultores" subtitle="Lista común a todos los galpones. Cada galpón se vincula a una o a varias desde su ficha, y salen impresas en sus etiquetas, facturas y remitos.">
        <x-slot:actions>
            <a href="{{ route('superadmin.associations.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nueva asociación</a>
        </x-slot:actions>
    </x-page-header>

    @include('superadmin._nav')

    <x-table>
        <thead><tr><th>Asociación</th><th>Localidad</th><th>CUIT</th><th>Galpones vinculados</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @forelse ($associations as $a)
                <tr>
                    <td>
                        <span class="font-medium">{{ $a->name }}</span>
                        @if ($a->short_name)<span class="block text-xs text-stone-500">{{ $a->short_name }}</span>@endif
                    </td>
                    <td class="text-sm">{{ trim(($a->locality ?? '').($a->province ? ', '.$a->province : ''), ', ') ?: '—' }}</td>
                    <td class="text-sm tabular-nums">{{ $a->cuit ? \App\Rules\Cuit::format($a->cuit) : '—' }}</td>
                    <td class="text-sm">
                        @forelse ($a->warehouses as $w)
                            <a href="{{ route('superadmin.warehouses.show', $w) }}" class="link">{{ $w->name }}</a>@if (! $loop->last), @endif
                        @empty
                            <span class="text-stone-400">Ninguno</span>
                        @endforelse
                    </td>
                    <td><x-badge :color="$a->active ? 'emerald' : 'zinc'">{{ $a->active ? 'Activa' : 'Inactiva' }}</x-badge></td>
                    <td class="text-right"><a href="{{ route('superadmin.associations.edit', $a) }}" class="link">Editar</a></td>
                </tr>
            @empty
                <x-empty colspan="6" message="Todavía no cargaste asociaciones. Tocá «Nueva asociación»."/>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
