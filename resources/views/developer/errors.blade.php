<x-layouts.app title="Errores del sistema">
    <x-page-header title="Panel desarrollador" subtitle="Errores técnicos registrados con su código ERR-…"/>
    @include('developer._nav')

    <x-filters>
        <x-input name="q" label="Código, mensaje o URL" :value="request('q')"/>
        <x-select name="category" label="Categoría" :options="$categories" :value="request('category')" placeholder="Todas"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Código</th><th>Fecha</th><th>Categoría</th><th>Mensaje</th><th>Usuario</th></tr></thead>
        <tbody>
            @forelse ($items as $e)
                <tr>
                    <td><a href="{{ route('developer.errors.show', $e) }}" class="link code">{{ $e->code }}</a></td>
                    <td class="whitespace-nowrap">{{ fdate($e->created_at, true) }}</td>
                    <td><x-badge color="stone">{{ $e->category }}</x-badge></td>
                    <td class="max-w-md truncate text-sm">{{ $e->message }}</td>
                    <td class="text-stone-500">{{ $e->user?->full_name ?? '—' }}</td>
                </tr>
            @empty
                <x-empty colspan="5" message="Sin errores registrados."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
