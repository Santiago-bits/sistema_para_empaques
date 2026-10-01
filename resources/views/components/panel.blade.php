@props(['title' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'panel']) }}>
    @if ($title || isset($actions))
        <header class="panel-header">
            <h2 class="panel-title">{{ $title }}</h2>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-4' => $padding])>
        {{ $slot }}
    </div>
</section>
