@php
    $accountTypes = \App\Models\AccountMovement::TYPES;
    $reasons = \App\Models\ContainerMovement::REASONS;
    $kindColors = ['money' => 'emerald', 'containers' => 'sky', 'lots' => 'amber', 'loads' => 'violet'];
    $kindLabels = ['money' => 'Plata', 'containers' => 'Envases', 'lots' => 'Ingreso de fruta', 'loads' => 'Carga'];
    $currentKind = request('kind');
    $filters = request()->only(['from', 'to']);
    $balance = $summary['balance'];
    $lower = mb_strtolower($typeLabel);
    $defaultDirection = old('direction', 'delivered');
    $defaultReason = old('reason', 'loan');
@endphp
<x-layouts.app :title="$name.' · Movimientos'">
    <x-page-header :title="$name" :subtitle="$typeLabel.' · Todos sus movimientos'" :back="$backUrl">
        <x-slot:actions>
            @if ($balance !== null && Route::has('accounts.show'))
                <a href="{{ route('accounts.show', [$type, $holder->getKey()]) }}" class="btn btn-secondary"><x-icon name="book" class="size-4"/> Cuenta corriente</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Resumen: lo más importante, en grande. --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @if ($balance !== null)
            <div @class(['panel p-4', 'ring-2 ring-amber-500/40' => $balance > 0.004, 'ring-2 ring-sky-500/40' => $balance < -0.004])>
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Plata</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-stone-900 dark:text-white">{{ money(abs($balance)) }}</p>
                <p class="text-sm text-stone-500 dark:text-stone-400">
                    @if ($balance > 0.004) Nos debe @elseif ($balance < -0.004) Le debemos @else Está al día @endif
                </p>
            </div>
        @endif
        <div class="panel p-4" data-summary="containers">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Bines y envases</p>
            @forelse ($summary['containers'] as $row)
                <p class="mt-1 text-sm text-stone-700 dark:text-stone-300">
                    <span class="text-2xl font-bold tabular-nums text-stone-900 dark:text-white">{{ num(abs($row->balance)) }}</span>
                    {{ $row->name }}
                    <span class="block text-xs text-stone-500">{{ $row->balance > 0 ? 'Los tiene él (son nuestros)' : 'Se los debemos' }}</span>
                </p>
            @empty
                <p class="mt-1 text-2xl font-bold text-stone-900 dark:text-white">0</p>
                <p class="text-sm text-stone-500 dark:text-stone-400">No tiene envases pendientes</p>
            @endforelse
        </div>
        @if ($summary['lots'])
            <div class="panel p-4">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Fruta que ingresó</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-stone-900 dark:text-white">{{ kg($summary['lots']->kg) }}</p>
                <p class="text-sm text-stone-500 dark:text-stone-400">{{ num($summary['lots']->count) }} ingresos · {{ num($summary['lots']->bins) }} bines</p>
            </div>
        @endif
        @if ($summary['loads'])
            <div class="panel p-4">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Cargas</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-stone-900 dark:text-white">{{ num($summary['loads']->count) }}</p>
                <p class="text-sm text-stone-500 dark:text-stone-400">{{ num($summary['loads']->crates) }} cajones · {{ kg($summary['loads']->kg) }}</p>
            </div>
        @endif
    </div>

    @can('containers.manage')
        <x-panel title="Registrar bines o envases" class="mb-6">
            <form method="POST" action="{{ route('holders.containers.store', [$type, $holder->getKey()]) }}" class="space-y-4"
                  x-data="{ direction: @js($defaultDirection), reason: @js($defaultReason), qty: @js(old('quantity', '')), price: @js(old('unit_price', '')) }">
                @csrf
                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Movimiento">
                    <label class="cursor-pointer rounded-lg border px-4 py-3" :class="direction === 'delivered' ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40' : 'border-stone-200 dark:border-stone-700'">
                        <input type="radio" name="direction" value="delivered" x-model="direction" class="sr-only">
                        <span class="block font-semibold text-stone-900 dark:text-white">Le entregamos envases</span>
                        <span class="text-sm text-stone-500 dark:text-stone-400">Se los lleva el {{ $lower }}</span>
                    </label>
                    <label class="cursor-pointer rounded-lg border px-4 py-3" :class="direction === 'received' ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40' : 'border-stone-200 dark:border-stone-700'">
                        <input type="radio" name="direction" value="received" x-model="direction" class="sr-only">
                        <span class="block font-semibold text-stone-900 dark:text-white">Nos entregó envases</span>
                        <span class="text-sm text-stone-500 dark:text-stone-400">Los trae o los devuelve</span>
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <x-select name="container_type_id" label="Tipo de envase" :options="$containerTypes" placeholder="Bin / envase (sin especificar)"/>
                    <x-input name="quantity" type="number" min="1" step="1" label="Cantidad" required x-model="qty"/>
                    <x-input name="date" type="date" label="Fecha" :value="old('date', today()->toDateString())" :max="today()->toDateString()"/>
                </div>

                @if ($canPayWithContainers)
                    <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Motivo">
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border px-4 py-3" :class="reason === 'loan' ? 'border-brand-500' : 'border-stone-200 dark:border-stone-700'">
                            <input type="radio" name="reason" value="loan" x-model="reason" class="mt-1">
                            <span><span class="block font-medium text-stone-900 dark:text-white">Préstamo o devolución</span><span class="text-sm text-stone-500 dark:text-stone-400">No cambia la plata</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border px-4 py-3" :class="reason === 'payment' ? 'border-brand-500' : 'border-stone-200 dark:border-stone-700'">
                            <input type="radio" name="reason" value="payment" x-model="reason" class="mt-1">
                            <span><span class="block font-medium text-stone-900 dark:text-white">Como parte de pago</span><span class="text-sm text-stone-500 dark:text-stone-400">Se descuenta de la cuenta corriente</span></span>
                        </label>
                    </div>
                    <div x-show="reason === 'payment'" x-cloak class="grid gap-4 md:grid-cols-3">
                        <x-input name="unit_price" inputmode="decimal" label="Valor de cada envase ($)" x-model="price" x-bind:required="reason === 'payment'"/>
                        <div class="md:col-span-2 self-end rounded-lg bg-stone-50 px-4 py-3 text-sm dark:bg-stone-800/60">
                            <span x-text="(direction === 'received' ? 'Se le descuenta de lo que nos debe: ' : 'Se le suma a lo que nos debe: ') + new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format((parseFloat(String(price).replace(/\./g, '').replace(',', '.')) || 0) * (parseInt(qty) || 0))"></span>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="reason" value="loan">
                @endif

                <x-input name="description" label="Detalle (opcional)" maxlength="255" placeholder="Se completa solo si lo dejás vacío"/>
                <div class="flex justify-end">
                    <button class="btn btn-primary btn-lg">Registrar</button>
                </div>
            </form>
        </x-panel>
    @endcan

    {{-- Filtros: qué mostrar y entre qué fechas. --}}
    <div class="mb-4 flex flex-wrap items-end gap-1 border-b border-stone-200 dark:border-stone-800" role="tablist" aria-label="Qué mostrar">
        <a href="{{ route('holders.activity', [$type, $holder->getKey()] + $filters) }}" @class(['tab', 'active' => ! $currentKind]) @if (! $currentKind) aria-current="page" @endif>Todo</a>
        @foreach ($kinds as $key => $label)
            <a href="{{ route('holders.activity', [$type, $holder->getKey(), 'kind' => $key] + $filters) }}" @class(['tab', 'active' => $currentKind === $key]) @if ($currentKind === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <form method="GET" class="ml-auto flex flex-wrap items-end gap-2 pb-2" data-allow-resubmit>
            @if ($currentKind)<input type="hidden" name="kind" value="{{ $currentKind }}">@endif
            <x-input name="from" type="date" label="Desde" :value="request('from')"/>
            <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
            <button class="btn btn-secondary btn-sm"><x-icon name="filter" class="size-4"/> Ver</button>
        </form>
    </div>

    <x-table>
        <thead><tr><th>Fecha</th><th>Qué pasó</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Envases</th><th class="num">Cajones</th><th class="num">Kg</th><th></th></tr></thead>
        <tbody>
            @forelse ($movements as $row)
                <tr @class(['opacity-50' => $row->voided])>
                    <td class="whitespace-nowrap">{{ fdate(\Illuminate\Support\Carbon::parse($row->date)) }}</td>
                    <td class="min-w-64">
                        <x-badge :color="$kindColors[$row->kind]">{{ $kindLabels[$row->kind] }}</x-badge>
                        <span @class(['ml-1 text-stone-900 dark:text-white', 'line-through' => $row->voided])>
                            @switch($row->kind)
                                @case('money') {{ $accountTypes[$row->ref] ?? $row->ref }} · {{ $row->description }} @break
                                @case('containers') {{ $row->description }} @break
                                @case('lots')
                                    Ingreso <span class="code">{{ $row->ref }}</span>{{ $row->qty ? ' · '.num($row->qty).' bines' : '' }}
                                    @break
                                @case('loads') Carga <span class="code">{{ $row->ref }}</span> @break
                            @endswitch
                        </span>
                        @if ($row->kind === 'containers')
                            <span class="block text-xs text-stone-500">{{ $reasons[$row->ref] ?? '' }}</span>
                        @endif
                        @if ($row->voided)
                            <span class="block text-xs text-red-600 dark:text-red-400">Anulado</span>
                        @endif
                        @if ($row->kind === 'lots' && Route::has('lots.show') && auth()->user()->can('lots.view'))
                            <a href="{{ route('lots.show', $row->ref_id) }}" class="link block text-xs">Ver ingreso</a>
                        @elseif ($row->kind === 'loads' && Route::has('loads.show') && auth()->user()->can('loads.view'))
                            <a href="{{ route('loads.show', $row->ref_id) }}" class="link block text-xs">Ver carga</a>
                        @endif
                    </td>
                    <td class="num font-semibold text-red-600 dark:text-red-400">{{ (float) $row->debit ? money($row->debit) : '' }}</td>
                    <td class="num font-semibold text-emerald-600 dark:text-emerald-400">{{ (float) $row->credit ? money($row->credit) : '' }}</td>
                    <td class="num whitespace-nowrap">
                        @if ($row->kind === 'containers')
                            <span @class(['font-semibold', 'text-amber-700 dark:text-amber-400' => $row->qty > 0, 'text-emerald-700 dark:text-emerald-400' => $row->qty < 0])>{{ $row->qty > 0 ? '+' : '−' }}{{ num(abs($row->qty)) }}</span>
                        @endif
                    </td>
                    <td class="num">{{ in_array($row->kind, ['lots', 'loads'], true) ? num($row->crates) : '' }}</td>
                    <td class="num">{{ in_array($row->kind, ['lots', 'loads'], true) && (float) $row->kg ? kg($row->kg) : '' }}</td>
                    <td class="text-right">
                        @if ($row->kind === 'containers' && ! $row->voided)
                            @can('containers.manage')
                                <x-void-button :action="route('holders.containers.void', [$type, $holder->getKey(), $row->ref_id])" title="¿Anular este movimiento de envases? Si fue parte de pago, también se anula en la cuenta."/>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="8" message="Todavía no hay movimientos."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $movements->links() }}</x-slot:footer>
    </x-table>
    <p class="form-hint mt-2">
        Debe: suma a lo que nos debe. Haber: resta de lo que nos debe (o suma a lo que le debemos).
        Envases: <strong>+</strong> se los entregamos · <strong>−</strong> nos los entregó.
    </p>
</x-layouts.app>
