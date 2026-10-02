<x-layouts.app title="Notificaciones">
    <x-page-header title="Notificaciones" :subtitle="$unread ? $unread.' sin leer' : 'Estás al día'">
        <x-slot:actions>
            <div class="flex rounded-lg border border-stone-300 p-0.5 text-sm dark:border-stone-700">
                <a href="{{ route('notifications.index') }}" @class(['rounded-md px-3 py-1.5', 'bg-stone-900 text-white dark:bg-white dark:text-stone-900' => request('filter') !== 'unread'])>Todas</a>
                <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" @class(['rounded-md px-3 py-1.5', 'bg-stone-900 text-white dark:bg-white dark:text-stone-900' => request('filter') === 'unread'])>Sin leer</a>
            </div>
            @if ($unread)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="btn btn-secondary"><x-icon name="check" class="size-4"/> Marcar todas como leídas</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="panel divide-y divide-stone-200 dark:divide-stone-800">
        @forelse ($notifications as $n)
            <div @class(['flex items-start gap-4 p-4', 'bg-brand-50/60 dark:bg-brand-950/20' => ! $n->read_at])>
                <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-brand-600' => ! $n->read_at, 'bg-transparent' => $n->read_at]) aria-hidden="true"></span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-stone-900 dark:text-white">{{ $n->data['title'] ?? 'Aviso' }}</p>
                    <p class="mt-0.5 text-sm text-stone-600 dark:text-stone-300">{{ $n->data['message'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-stone-500 tabular-nums">{{ fdate($n->created_at, true) }} · {{ $n->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if (! empty($n->data['url']))
                        <form method="POST" action="{{ route('notifications.open', $n->id) }}">
                            @csrf
                            <button class="btn btn-secondary btn-sm">Abrir</button>
                        </form>
                    @elseif (! $n->read_at)
                        <form method="POST" action="{{ route('notifications.open', $n->id) }}">
                            @csrf
                            <button class="btn btn-ghost btn-sm">Marcar leída</button>
                        </form>
                    @endif
                    @if ($n->read_at)
                        <form method="POST" action="{{ route('notifications.unread', $n->id) }}">
                            @csrf
                            <button class="btn btn-ghost btn-sm" title="Marcar como no leída">No leída</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="p-10 text-center text-sm text-stone-500">No hay notificaciones{{ request('filter') === 'unread' ? ' sin leer' : '' }}.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
