{{-- Logo del sistema: rodaja de naranja con hoja sobre verde. Mismo dibujo que los íconos de la app (public/icons). --}}
@php $gid = 'lg'.substr(md5(uniqid('', true)), 0, 6); @endphp
<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" {{ $attributes->merge(['class' => 'size-8']) }} aria-hidden="true">
    <defs>
        <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#22c55e"/>
            <stop offset="1" stop-color="#15803d"/>
        </linearGradient>
    </defs>
    <rect width="64" height="64" rx="15" fill="url(#{{ $gid }})"/>
    <ellipse cx="46.5" cy="15.5" rx="9.5" ry="4.2" transform="rotate(-32 46.5 15.5)" fill="#bbf7d0"/>
    <path d="M38.5 20.5 L54.5 10.5" stroke="#4ade80" stroke-width="1.2" stroke-linecap="round"/>
    <circle cx="30" cy="35" r="19" fill="#f97316"/>
    <circle cx="30" cy="35" r="16.6" fill="#ffedd5"/>
    <circle cx="30" cy="35" r="15" fill="#fb923c"/>
    <g stroke="#ffedd5" stroke-width="1.7" stroke-linecap="round">
        <path d="M30 35 L45 35"/><path d="M30 35 L40.6 45.6"/><path d="M30 35 L30 50"/><path d="M30 35 L19.4 45.6"/>
        <path d="M30 35 L15 35"/><path d="M30 35 L19.4 24.4"/><path d="M30 35 L30 20"/><path d="M30 35 L40.6 24.4"/>
    </g>
    <circle cx="30" cy="35" r="2.4" fill="#ffedd5"/>
</svg>
