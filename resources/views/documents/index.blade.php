<x-layouts.app title="Documentos">
    <x-page-header title="Documentación" subtitle="Archivos adjuntos a cargas, remitos, comprobantes, clientes, lotes y pallets. Se guardan en el servidor (no públicos)."/>

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')"/>
        <x-select name="type" label="Tipo" :options="$types" :value="request('type')" placeholder="Todos"/>
        <x-select name="entity" label="Asociado a" :options="$entities" :value="request('entity')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Documento</th><th>Tipo</th><th>Asociado a</th><th>Subido</th><th class="num">Tamaño</th><th></th></tr></thead>
        <tbody>
            @forelse ($documents as $doc)
                @php
                    $entity = $doc->documentable;
                    $label = $entity ? ($entity->number ?? $entity->code ?? $entity->business_name ?? '#'.$entity->getKey()) : '—';
                @endphp
                <tr>
                    <td><a href="{{ route('documents.download', [$doc, 'inline' => 1]) }}" target="_blank" class="link">{{ $doc->title }}</a>
                        <p class="text-xs text-stone-500">{{ $doc->original_name }}</p></td>
                    <td>{{ $types[$doc->type] ?? $doc->type }}</td>
                    <td>{{ $entities[$doc->documentable_type] ?? $doc->documentable_type }} <span class="code">{{ $label }}</span></td>
                    <td class="tabular-nums">{{ fdate($doc->created_at, true) }} · {{ $doc->uploader?->full_name }}</td>
                    <td class="num">{{ num($doc->size / 1024, 0) }} KB</td>
                    <td class="text-right"><a href="{{ route('documents.download', $doc) }}" class="link">Descargar</a></td>
                </tr>
            @empty
                <x-empty colspan="6"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $documents->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
