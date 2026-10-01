@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Paginación">
        <p class="text-stone-500 dark:text-stone-400">
            @if ($paginator->total() > 0)
                Mostrando <span class="font-medium tabular-nums">{{ $paginator->firstItem() }}</span>–<span class="font-medium tabular-nums">{{ $paginator->lastItem() }}</span>
                de <span class="font-medium tabular-nums">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
            @endif
        </p>
        @if ($paginator->hasPages())
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="btn btn-secondary btn-sm opacity-40">‹</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm" rel="prev">‹</a>
                @endif
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-2 text-stone-400">…</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="btn btn-primary btn-sm tabular-nums" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="btn btn-secondary btn-sm tabular-nums">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm" rel="next">›</a>
                @else
                    <span class="btn btn-secondary btn-sm opacity-40">›</span>
                @endif
            </div>
        @endif
    </nav>
@endif
