@props(['fallback' => null])
{{-- «Volver» de las pantallas de impresión (etiquetas, credenciales, romaneo…): vuelve a donde estabas. --}}
@php
    $previous = url()->previous();
    $href = $previous && $previous !== url()->current() ? $previous : ($fallback ?? route('home'));
@endphp
<a href="{{ $href }}"
   onclick="if (document.referrer.indexOf(location.origin) === 0 && history.length > 1) { history.back(); return false; }"
   style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:0;border-radius:6px;background:#57534e;color:#fff;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer">
    &larr; Volver
</a>
