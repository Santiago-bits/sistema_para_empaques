<x-layouts.app :title="'Administración general · '.$license->client_name">
    <x-page-header :title="$license->client_name" :subtitle="$license->locality" :back="route('superadmin.clients.index')">
        <x-slot:actions>
            <a href="{{ route('central.clients.show', $license) }}" class="btn btn-secondary"><x-icon name="chart-line" class="size-4"/> Uso detallado y licencia</a>
        </x-slot:actions>
    </x-page-header>

    @include('superadmin._nav')

    @php $r = $license->latestReport; @endphp
    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat label="Estado de pago" :value="$license->paymentLabel()" icon="currency" :color="['green' => 'brand', 'amber' => 'amber', 'red' => 'red'][$license->paymentColor()] ?? 'stone'"
                :hint="$license->paid_until ? 'Pagado hasta '.fdate($license->paid_until) : null"/>
        <x-stat label="Cuota mensual" :value="$license->monthly_fee ? money($license->monthly_fee, $license->fee_currency) : 'Sin cuota'" icon="receipt" color="stone"/>
        <x-stat label="Conexión" :value="$license->isOnline() ? 'Conectado' : ($license->last_seen_at ? $license->last_seen_at->diffForHumans() : 'Nunca')" icon="signal" :color="$license->isOnline() ? 'sky' : 'stone'"/>
        <x-stat label="Usuarios activos (7 d)" :value="$r ? num($r->metric('users_active_7d')).' de '.num($r->metric('users_total')) : '—'" icon="users" color="violet"
                :hint="$r ? num($r->metric('crates_30d')).' cajones en 30 días' : null"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Pagos" class="lg:col-span-2">
            <x-table>
                <thead><tr><th>Fecha</th><th class="num">Importe</th><th>Cubre</th><th>Forma</th><th>Comprobante</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr @class(['opacity-50' => $payment->isVoided()])>
                            <td class="whitespace-nowrap tabular-nums">{{ fdate($payment->paid_at) }}</td>
                            <td class="num whitespace-nowrap">{{ money($payment->amount, $payment->currency) }}</td>
                            <td class="text-sm whitespace-nowrap">{{ fdate($payment->period_from) }} al {{ fdate($payment->period_to) }}
                                <span class="block text-xs text-stone-500">{{ $payment->months }} {{ $payment->months === 1 ? 'mes' : 'meses' }}</span></td>
                            <td class="text-sm">{{ $payment->methodLabel() }}</td>
                            <td class="text-sm">{{ $payment->reference ?: '—' }}
                                @if ($payment->isVoided())
                                    <span class="block text-xs text-red-600 dark:text-red-400">Anulado: {{ $payment->void_reason }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @unless ($payment->isVoided())
                                    <x-void-button :action="route('superadmin.clients.payments.void', [$license, $payment])" label="Anular" :title="'¿Anular el pago del '.fdate($payment->paid_at).'?'"/>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="6" message="Sin pagos registrados."/>
                    @endforelse
                </tbody>
            </x-table>
        </x-panel>

        <div class="space-y-6">
            <x-panel title="Registrar un pago">
                <form method="POST" action="{{ route('superadmin.clients.payments.store', $license) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-3 gap-3">
                        <x-input name="amount" label="Importe" inputmode="decimal" :value="old('amount', $license->monthly_fee ? num($license->monthly_fee, 2) : null)" required class="col-span-2"/>
                        <x-select name="currency" label="Moneda" :options="['ARS' => 'ARS', 'USD' => 'USD']" :value="old('currency', $license->fee_currency ?? 'ARS')"/>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-input name="paid_at" type="date" label="Fecha de pago" :value="old('paid_at', today()->toDateString())" required/>
                        <x-input name="months" type="number" min="1" max="36" label="Meses que paga" :value="old('months', 1)" required/>
                    </div>
                    <x-select name="method" label="Forma de pago" :options="$methods" :value="old('method', 'transfer')"/>
                    <x-input name="reference" label="Comprobante (opcional)" :value="old('reference')" placeholder="N° de transferencia, recibo…"/>
                    <button class="btn btn-primary w-full"><x-icon name="check" class="size-4"/> Registrar pago</button>
                    <p class="text-xs text-stone-500">
                        {{ $license->paid_until && $license->paid_until->gte(today()->subDay()) ? 'Se suma a continuación: hoy está pagado hasta el '.fdate($license->paid_until).'.' : 'Cubre desde la fecha de pago.' }}
                    </p>
                </form>
            </x-panel>

            <x-panel title="Cuota mensual">
                <form method="POST" action="{{ route('superadmin.clients.fee', $license) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-3 gap-3">
                        <x-input name="monthly_fee" label="Importe por mes" type="number" step="0.01" min="0" :value="old('monthly_fee', $license->monthly_fee)" class="col-span-2" hint="Vacío = sin cuota"/>
                        <x-select name="fee_currency" label="Moneda" :options="['ARS' => 'ARS', 'USD' => 'USD']" :value="old('fee_currency', $license->fee_currency ?? 'ARS')"/>
                    </div>
                    <button class="btn btn-secondary w-full">Guardar cuota</button>
                </form>
            </x-panel>

            <x-panel title="Contacto">
                <x-dl :items="['Responsable' => $license->contact_name, 'Teléfono' => $license->contact_phone, 'Email' => $license->contact_email, 'Localidad' => $license->locality]"/>
                <a href="{{ route('central.clients.edit', $license) }}" class="link mt-2 inline-block text-sm">Editar datos del cliente</a>
            </x-panel>
        </div>
    </div>
</x-layouts.app>
