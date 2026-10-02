{{-- Lista de atajos (pantalla «Ayuda y atajos» y ventana de la tecla «?»). $groups: Shortcuts::for()['groups']. --}}
<div class="grid gap-x-8 gap-y-6 lg:grid-cols-2">
    @foreach ($groups as $group)
        <section class="min-w-0">
            <h2 class="mb-2 text-xs font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">{{ $group['title'] }}</h2>
            <dl class="divide-y divide-stone-100 dark:divide-stone-800">
                @foreach ($group['items'] as $item)
                    <div class="flex items-start gap-3 py-1.5">
                        <dt class="flex w-32 shrink-0 flex-wrap items-center gap-1">
                            @foreach ($item['keys'] as $key)
                                @if (! $loop->first)
                                    <span class="text-[10px] text-stone-400">{{ ($item['keys'][0] === 'G' && count($item['keys']) === 2) ? 'luego' : '+' }}</span>
                                @endif
                                <kbd class="kbd">{{ $key }}</kbd>
                            @endforeach
                        </dt>
                        <dd class="min-w-0 text-sm text-stone-700 dark:text-stone-300">{{ $item['description'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
