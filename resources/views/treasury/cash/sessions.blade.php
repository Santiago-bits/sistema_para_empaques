<x-layouts.app title="Cierres de caja">
    <x-page-header title="Cierres de caja" subtitle="Cada apertura y cierre con su arqueo." :back="route('cash.index')"/>

    @include('treasury._nav')

    <x-table>
        <thead><tr><th>Apertura</th><th>Cierre</th><th class="num">Saldo inicial</th><th class="num">Esperado</th><th class="num">Contado</th><th class="num">Diferencia</th><th>Responsables</th><th></th></tr></thead>
        <tbody>
            @forelse ($sessions as $s)
                <tr>
                    <td class="whitespace-nowrap">{{ fdate($s->opened_at, true) }}</td>
                    <td class="whitespace-nowrap">{!! $s->closed_at ? e(fdate($s->closed_at, true)) : '<span class="font-semibold text-emerald-700 dark:text-emerald-400">Abierta</span>' !!}</td>
                    <td class="num">{{ money($s->opening_balance) }}</td>
                    <td class="num">{{ $s->expected_balance !== null ? money($s->expected_balance) : '—' }}</td>
                    <td class="num">{{ $s->counted_balance !== null ? money($s->counted_balance) : '—' }}</td>
                    <td @class(['num font-semibold', 'text-red-600 dark:text-red-400' => $s->difference !== null && (float) $s->difference < 0, 'text-amber-600 dark:text-amber-400' => $s->difference !== null && (float) $s->difference > 0])>
                        {{ $s->difference !== null ? money($s->difference) : '—' }}
                    </td>
                    <td class="text-xs text-stone-500">{{ $s->opener?->full_name }}@if ($s->closer) → {{ $s->closer->full_name }}@endif</td>
                    <td class="text-right"><a href="{{ route('cash.show', $s) }}" class="link">Ver</a></td>
                </tr>
            @empty
                <x-empty colspan="8" message="Todavía no se abrió ninguna caja."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $sessions->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
