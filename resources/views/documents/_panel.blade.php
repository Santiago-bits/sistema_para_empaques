{{-- Panel reutilizable de documentos adjuntos: @include('documents._panel', ['documentable' => $modelo]) --}}
@php
    $docEntity = $documentable->getMorphClass();
    $docAllowed = module_enabled('documents') && auth()->user()->can('documents.view')
        && array_key_exists($docEntity, \App\Services\DocumentService::ENTITIES)
        && auth()->user()->can(\App\Services\DocumentService::ENTITIES[$docEntity][1]);
    $docItems = $docAllowed ? $documentable->documents()->with('uploader:id,first_name,last_name')->latest('id')->get() : collect();
@endphp
@if ($docAllowed)
    <x-panel title="Documentos adjuntos" :padding="false">
        <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
            @forelse ($docItems as $doc)
                <li class="flex items-center justify-between gap-3 px-4 py-2">
                    <div class="min-w-0">
                        <a href="{{ route('documents.download', [$doc, 'inline' => 1]) }}" target="_blank" class="link truncate">{{ $doc->title }}</a>
                        <p class="text-xs text-stone-500">{{ \App\Models\Document::TYPES[$doc->type] ?? $doc->type }} · {{ fdate($doc->created_at, true) }} · {{ $doc->uploader?->full_name }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <a href="{{ route('documents.download', $doc) }}" class="text-xs link">Descargar</a>
                        @can('documents.manage')
                            <form method="POST" action="{{ route('documents.destroy', $doc) }}" x-data x-confirm="¿Eliminar el documento {{ $doc->title }}?">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </form>
                        @endcan
                    </div>
                </li>
            @empty
                <li class="px-4 py-4 text-center text-stone-500">Sin documentos adjuntos.</li>
            @endforelse
        </ul>
        @can('documents.manage')
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="grid gap-3 border-t border-stone-200 p-4 sm:grid-cols-[1fr_1fr_auto] dark:border-stone-800">
                @csrf
                <input type="hidden" name="documentable_type" value="{{ $docEntity }}">
                <input type="hidden" name="documentable_id" value="{{ $documentable->getKey() }}">
                <select name="type" class="form-input" required aria-label="Tipo de documento">
                    @foreach (\App\Models\Document::TYPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input type="file" name="file" required class="form-input" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx" aria-label="Archivo">
                <button class="btn btn-secondary"><x-icon name="upload" class="size-4"/> Adjuntar</button>
                <input type="text" name="title" class="form-input sm:col-span-3" placeholder="Título (opcional)" maxlength="255">
            </form>
        @endcan
    </x-panel>
@endif
