<a href="{{ route($item['route']) }}" @class(['nav-item', 'active' => $item['active']]) @click="sidebar = false" @if ($item['active']) aria-current="page" @endif>
    <x-icon :name="$item['icon']" class="size-[18px] shrink-0 {{ ($item['highlight'] ?? false) ? 'text-accent-400' : 'text-stone-400' }}"/>
    <span class="truncate">{{ $item['label'] }}</span>
    @if ($item['route'] === 'alerts.index' && $openAlertsCount > 0)
        <span class="ml-auto rounded-full bg-accent-500 px-1.5 text-[11px] font-semibold text-white">{{ $openAlertsCount }}</span>
    @elseif ($fkey = $shortcuts['routeKeys'][$item['route']] ?? null)
        <span class="ml-auto hidden rounded border border-white/10 px-1 font-mono text-[10px] text-stone-500 lg:inline" title="Atajo de teclado">{{ $fkey }}</span>
    @endif
</a>
