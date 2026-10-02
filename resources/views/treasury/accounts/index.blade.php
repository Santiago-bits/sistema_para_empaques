@php
    $holderTypes = \App\Models\AccountMovement::HOLDERS;
@endphp
<x-layouts.app title="Cuentas corrientes">
    <x-page-header title="Cuentas corrientes" subtitle="Saldo de cada cliente, productor, transportista, proveedor y empleado.">
        <x-slot:actions>
            @can('accounts.manage')
                <form method="POST" action="{{ route('accounts.sync') }}">
                    @csrf
                    <button class="btn btn-secondary" title="Imputa las facturas autorizadas y los fletes que todavía no figuren en las cuentas"><x-icon name="refresh" class="size-4"/> Imputar pendientes</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('treasury._nav')

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        @foreach ($holderTypes as $key => [$singular, $plural])
            <a href="{{ route('accounts.index', ['type' => $key]) }}" @class(['panel block p-3 transition hover:border-brand-400', 'ring-2 ring-brand-500' => $type === $key])>
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $plural }}</p>
                <p class="mt-1 text-sm tabular-nums"><span class="text-stone-500">Nos deben</span> <strong class="text-emerald-700 dark:text-emerald-400">{{ money($totals[$key]['debtors']) }}</strong></p>
                <p class="text-sm tabular-nums"><span class="text-stone-500">Les debemos</span> <strong class="text-red-700 dark:text-red-400">{{ money($totals[$key]['creditors']) }}</strong></p>
            </a>
        @endforeach
    </div>

    <x-filters :exports="[['label' => 'Excel', 'format' => 'xlsx', 'route' => route('accounts.index')]]">
        <input type="hidden" name="type" value="{{ $type }}">
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Nombre, CUIT, código"/>
        <x-select name="only" label="Mostrar" :value="request('only')" placeholder="Todos"
                  :options="['nonzero' => 'Con saldo', 'debtors' => 'Nos deben', 'creditors' => 'Les debemos']"/>
    </x-filters>

    <x-table>
        <thead><tr><th>{{ $holderTypes[$type][0] }}</th><th>CUIT / DNI</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Saldo</th><th>Último mov.</th><th></th></tr></thead>
        <tbody>
            @forelse ($holders as $h)
                @php $balance = round((float) $h->balance, 2); @endphp
                <tr>
                    <td class="font-medium text-stone-900 dark:text-white">
                        <a href="{{ route('accounts.show', [$type, $h->getKey()]) }}" class="hover:underline">{{ \App\Models\AccountMovement::holderLabel($h) }}</a>
                        @if ($h->trashed()) <x-badge color="zinc">Eliminado</x-badge> @endif
                    </td>
                    <td class="code text-stone-500">{{ $h->cuit ?? $h->dni ?? '—' }}</td>
                    <td class="num">{{ money($h->total_debit) }}</td>
                    <td class="num">{{ money($h->total_credit) }}</td>
                    <td class="num">
                        @if (abs($balance) < 0.005)
                            <span class="text-stone-400">{{ money(0) }}</span>
                        @elseif ($balance > 0)
                            <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ money($balance) }}</span>
                            <span class="block text-[11px] text-stone-500">nos debe</span>
                        @else
                            <span class="font-semibold text-red-700 dark:text-red-400">{{ money(-$balance) }}</span>
                            <span class="block text-[11px] text-stone-500">le debemos</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-stone-500">{{ $h->last_date ? fdate(\Illuminate\Support\Carbon::parse($h->last_date)) : '—' }}</td>
                    <td class="text-right"><a href="{{ route('accounts.show', [$type, $h->getKey()]) }}" class="link whitespace-nowrap">Ver cuenta</a></td>
                </tr>
            @empty
                <x-empty colspan="7" message="No hay {{ mb_strtolower($holderTypes[$type][1]) }} con esos filtros."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $holders->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
