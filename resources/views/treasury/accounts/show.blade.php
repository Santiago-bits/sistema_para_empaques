@php
    [$singular, $plural] = \App\Models\AccountMovement::HOLDERS[$type];
    $types = \App\Models\AccountMovement::TYPES;
    $defaultDirection = old('direction', $type === 'client' ? 'collection' : 'payment');
    $portfolioOptions = $portfolio->mapWithKeys(fn ($c) => [$c->id => $c->bank.' N° '.$c->number.' · '.money($c->amount).' · cobro '.fdate($c->payment_date)])->all();
    $period = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
@endphp
<x-layouts.app :title="'Cuenta corriente · '.$label">
    <x-page-header :title="$label" :subtitle="'Cuenta corriente · '.$singular.($holder->cuit ? ' · CUIT '.$holder->cuit : '')"
                   :back="route('accounts.index', ['type' => $type])">
        <x-slot:actions>
            <a href="{{ route('accounts.print', [$type, $holder->getKey()] + $period) }}" class="btn btn-secondary"><x-icon name="printer" class="size-4"/> Imprimir resumen</a>
            <a href="{{ route('accounts.show', [$type, $holder->getKey()] + $period + ['format' => 'xlsx']) }}" class="btn btn-secondary"><x-icon name="download" class="size-4"/> Excel</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div @class(['panel col-span-2 p-4', 'ring-2 ring-emerald-500/40' => $balance > 0.004, 'ring-2 ring-red-500/40' => $balance < -0.004])>
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Saldo actual</p>
            <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-emerald-700 dark:text-emerald-400' => $balance > 0.004, 'text-red-700 dark:text-red-400' => $balance < -0.004])>{{ money(abs($balance)) }}</p>
            <p class="text-sm text-stone-500">
                @if ($balance > 0.004) {{ $singular }} nos debe @elseif ($balance < -0.004) Le debemos al {{ mb_strtolower($singular) }} @else Cuenta saldada @endif
            </p>
        </div>
        <x-stat label="Debe del período" :value="money($statement['debit'])" color="stone"/>
        <x-stat label="Haber del período" :value="money($statement['credit'])" color="stone"/>
    </div>

    @can('accounts.manage')
        <div class="mb-6 grid gap-6 xl:grid-cols-3">
            <x-panel title="Registrar cobro o pago" class="xl:col-span-2">
                <form method="POST" action="{{ route('accounts.payments.store', [$type, $holder->getKey()]) }}" class="space-y-4"
                      x-data="{ direction: {{ \Illuminate\Support\Js::from($defaultDirection) }}, method: {{ \Illuminate\Support\Js::from(old('method', 'cash')) }}, checkMode: {{ \Illuminate\Support\Js::from(old('endorse_check_id') ? 'endorse' : 'new') }} }">
                    @csrf
                    <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Operación">
                        <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm" :class="direction === 'collection' ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40' : 'border-stone-200 dark:border-stone-700'">
                            <input type="radio" name="direction" value="collection" x-model="direction" class="sr-only">
                            <span class="block font-semibold">Cobro</span><span class="text-xs text-stone-500">Nos paga: baja lo que nos debe</span>
                        </label>
                        <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm" :class="direction === 'payment' ? 'border-red-500 bg-red-50 dark:bg-red-950/40' : 'border-stone-200 dark:border-stone-700'">
                            <input type="radio" name="direction" value="payment" x-model="direction" class="sr-only">
                            <span class="block font-semibold">Pago</span><span class="text-xs text-stone-500">Le pagamos: baja lo que le debemos</span>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <x-select name="method" label="Medio" :options="\App\Models\AccountMovement::METHODS" x-model="method" required/>
                        <div x-show="!(method === 'check' && direction === 'payment' && checkMode === 'endorse')">
                            <x-input name="amount" inputmode="decimal" label="Importe" hint="Ej.: 250.000,00"/>
                        </div>
                        <x-input name="date" type="date" label="Fecha" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                        @if ($type === 'employee')
                            <div x-show="direction === 'payment'">
                                <x-select name="type" label="Concepto" :options="['payment' => 'Pago de sueldo / jornal', 'advance' => 'Adelanto']" :value="old('type', 'payment')"/>
                            </div>
                        @endif
                        <div class="md:col-span-2"><x-input name="description" label="Descripción (opcional)" maxlength="255" placeholder="Se completa sola si la dejás vacía"/></div>
                        <x-input name="reference" label="Referencia" maxlength="80" placeholder="N° de transferencia, recibo…"/>
                    </div>

                    @unless ($cashOpen)
                        <p x-show="method === 'cash'" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            La caja está cerrada: para cobrar o pagar en efectivo, primero <a href="{{ route('cash.index') }}" class="link">abrí la caja</a>.
                        </p>
                    @endunless

                    <div x-show="method === 'check'" x-cloak class="space-y-4 rounded-lg border border-stone-200 p-3 dark:border-stone-700">
                        <div x-show="direction === 'payment'" class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" value="new" x-model="checkMode"> Cheque propio (lo emitimos)</label>
                            <label class="flex items-center gap-2"><input type="radio" value="endorse" x-model="checkMode" @disabled($portfolio->isEmpty())> Endosar un cheque de cartera ({{ $portfolio->count() }})</label>
                        </div>
                        <div x-show="direction === 'payment' && checkMode === 'endorse'">
                            <x-select name="endorse_check_id" label="Cheque a endosar" :options="$portfolioOptions" placeholder="Elegir…" x-bind:disabled="!(method === 'check' && direction === 'payment' && checkMode === 'endorse')"/>
                        </div>
                        <div x-show="!(direction === 'payment' && checkMode === 'endorse')" class="grid gap-4 md:grid-cols-3">
                            <x-input name="check[bank]" label="Banco" maxlength="80"/>
                            <x-input name="check[number]" label="Número" maxlength="30"/>
                            <x-input name="check[payment_date]" type="date" label="Fecha de cobro"/>
                            <x-input name="check[issued_on]" type="date" label="Fecha de emisión" :value="today()->toDateString()"/>
                            <div x-show="direction === 'collection'"><x-input name="check[issuer_name]" label="Librador" :placeholder="$label"/></div>
                            <div x-show="direction === 'collection'"><x-input name="check[issuer_cuit]" label="CUIT del librador" :placeholder="$holder->cuit"/></div>
                            <x-checkbox name="check[electronic]" label="E-cheq (electrónico)"/>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button class="btn btn-primary"><span x-text="direction === 'collection' ? 'Registrar cobro' : 'Registrar pago'">Registrar</span></button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="Ajuste o saldo inicial">
                <form method="POST" action="{{ route('accounts.adjustments.store', [$type, $holder->getKey()]) }}" class="space-y-4">
                    @csrf
                    <x-select name="kind" label="Tipo" required :value="old('kind', 'debit')" :options="[
                        'debit' => 'Cargo (nos debe más)',
                        'credit' => 'Ajuste a su favor (le debemos más)',
                        'opening_debit' => 'Saldo inicial: nos debe',
                        'opening_credit' => 'Saldo inicial: le debemos',
                        'advance' => 'Adelanto entregado (sin caja)',
                    ]"/>
                    <x-input name="amount" inputmode="decimal" label="Importe" required/>
                    <x-input name="date" type="date" label="Fecha" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                    <x-input name="description" label="Motivo / descripción" required maxlength="255"/>
                    <button class="btn btn-secondary w-full">Registrar ajuste</button>
                </form>
            </x-panel>
        </div>
    @endcan

    <form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-4" data-filters data-allow-resubmit>
        <x-input name="from" type="date" label="Desde" :value="$from->toDateString()"/>
        <x-input name="to" type="date" label="Hasta" :value="$to->toDateString()"/>
        <x-checkbox name="anulados" label="Ver anulados" :checked="request()->boolean('anulados')"/>
        <button class="btn btn-primary btn-sm"><x-icon name="filter" class="size-4"/> Ver período</button>
    </form>

    <x-table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Detalle</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Saldo</th><th></th></tr></thead>
        <tbody>
            <tr class="bg-stone-50 dark:bg-stone-900/50">
                <td>{{ fdate($from) }}</td><td></td><td class="font-medium">Saldo anterior</td><td></td><td></td>
                <td class="num font-semibold">{{ money($statement['previous']) }}</td><td></td>
            </tr>
            @forelse ($statement['rows'] as $row)
                <tr @class(['opacity-50' => $row->isVoided()])>
                    <td class="whitespace-nowrap">{{ fdate($row->date) }}</td>
                    <td><x-badge :color="$row->debit > 0 ? 'amber' : 'emerald'">{{ $types[$row->type] ?? $row->type }}</x-badge></td>
                    <td class="min-w-56">
                        <span @class(['font-medium text-stone-900 dark:text-white', 'line-through' => $row->isVoided()])>{{ $row->description }}</span>
                        <span class="block text-xs text-stone-500">
                            {{ $row->payment_method ? \App\Models\AccountMovement::METHODS[$row->payment_method] ?? '' : '' }}{{ $row->reference ? ' · '.$row->reference : '' }}{{ $row->user ? ' · '.$row->user->full_name : '' }}
                        </span>
                        @if ($row->isVoided())
                            <span class="block text-xs text-red-600 dark:text-red-400">Anulado: {{ $row->void_reason }}</span>
                        @endif
                        @include('treasury.accounts._source', ['row' => $row])
                    </td>
                    <td class="num">{{ (float) $row->debit ? money($row->debit) : '' }}</td>
                    <td class="num">{{ (float) $row->credit ? money($row->credit) : '' }}</td>
                    <td class="num font-medium">{{ $row->isVoided() ? '' : money($row->running_balance) }}</td>
                    <td class="text-right">
                        @if (! $row->isVoided() && ! in_array($row->source_type, ['check', 'invoice', 'lot', 'load'], true))
                            @can('accounts.manage')
                                <div x-data="{ open: false }" class="mb-1 inline-block text-left" @keydown.escape.stop="open = false">
                                    <button type="button" class="link text-sm" @click="open = ! open">Corregir</button>
                                    <form x-cloak x-show="open" method="POST" action="{{ route('accounts.movements.correct', $row) }}" class="mt-2 grid min-w-72 gap-2 rounded-lg border border-stone-200 bg-white p-2 text-left dark:border-stone-700 dark:bg-stone-900">
                                        @csrf @method('PUT')
                                        <input name="amount" inputmode="decimal" required class="form-input py-1.5 text-sm" value="{{ num((float) $row->debit ?: (float) $row->credit, 2) }}" aria-label="Importe">
                                        <input name="date" type="date" required class="form-input py-1.5 text-sm" value="{{ $row->date->toDateString() }}" max="{{ today()->toDateString() }}" aria-label="Fecha">
                                        <input name="description" required maxlength="255" class="form-input py-1.5 text-sm" value="{{ $row->description }}" aria-label="Detalle">
                                        <input name="reference" maxlength="80" class="form-input py-1.5 text-sm" value="{{ $row->reference }}" placeholder="Referencia" aria-label="Referencia">
                                        <input name="reason" required minlength="5" maxlength="255" class="form-input py-1.5 text-sm" placeholder="Motivo de la corrección" aria-label="Motivo">
                                        <div class="flex justify-end gap-2"><button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancelar</button><button class="btn btn-primary btn-sm">Guardar</button></div>
                                    </form>
                                </div>
                            @endcan
                        @endif
                        @if (! $row->isVoided() && $row->source_type !== 'check')
                            @can('accounts.void')
                                <x-void-button :action="route('accounts.movements.void', $row)" title="¿Anular este movimiento?"/>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="7" message="Sin movimientos en el período."/>
            @endforelse
        </tbody>
        <x-slot:footer>
            <div class="flex flex-wrap justify-end gap-6 text-sm tabular-nums">
                <span>Debe <strong>{{ money($statement['debit']) }}</strong></span>
                <span>Haber <strong>{{ money($statement['credit']) }}</strong></span>
                <span>Saldo al {{ fdate($to) }} <strong>{{ money($statement['balance']) }}</strong></span>
            </div>
        </x-slot:footer>
    </x-table>
    <p class="form-hint mt-2">Saldo positivo: el {{ mb_strtolower($singular) }} nos debe. Negativo: le debemos.</p>
</x-layouts.app>
