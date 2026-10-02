<x-layouts.app title="Nuevo cheque">
    <x-page-header title="Nuevo cheque" subtitle="Si elegís un titular, se registra como cobro (de terceros) o pago (propio) en su cuenta corriente."
                   :back="route('checks.index')"/>

    <form method="POST" action="{{ route('checks.store') }}" class="max-w-4xl space-y-6" x-data="{ kind: {{ \Illuminate\Support\Js::from(old('kind', 'third_party')) }} }">
        @csrf
        <x-panel>
            <div class="mb-5 grid grid-cols-2 gap-2" role="radiogroup" aria-label="Tipo de cheque">
                <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm" :class="kind === 'third_party' ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40' : 'border-stone-200 dark:border-stone-700'">
                    <input type="radio" name="kind" value="third_party" x-model="kind" class="sr-only">
                    <span class="block font-semibold">De terceros</span><span class="text-xs text-stone-500">Lo recibimos (entra a cartera)</span>
                </label>
                <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm" :class="kind === 'own' ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/40' : 'border-stone-200 dark:border-stone-700'">
                    <input type="radio" name="kind" value="own" x-model="kind" class="sr-only">
                    <span class="block font-semibold">Propio</span><span class="text-xs text-stone-500">Lo emitimos (queda por pagar)</span>
                </label>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <x-field label="Titular (opcional)" name="holder" hint="Cliente que lo entrega o a quién le pagamos.">
                    <select name="holder" id="holder" class="form-input">
                        <option value="">Sin titular</option>
                        @foreach ($holders as $group => $options)
                            @if ($options)
                                <optgroup label="{{ $group }}">
                                    @foreach ($options as $value => $name)
                                        <option value="{{ $value }}" @selected(old('holder') === $value)>{{ $name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                </x-field>
                <x-input name="bank" label="Banco" required maxlength="80"/>
                <x-input name="number" label="Número" required maxlength="30"/>
                <x-input name="amount" inputmode="decimal" label="Importe" required hint="Ej.: 1.250.000,00"/>
                <x-input name="issued_on" type="date" label="Fecha de emisión" :value="today()->toDateString()" required/>
                <x-input name="payment_date" type="date" label="Fecha de cobro" required/>
                <div x-show="kind === 'third_party'"><x-input name="issuer_name" label="Librador" maxlength="120"/></div>
                <div x-show="kind === 'third_party'"><x-input name="issuer_cuit" label="CUIT del librador" maxlength="13"/></div>
                <x-checkbox name="electronic" label="E-cheq (electrónico)"/>
                <div class="md:col-span-3"><x-input name="notes" label="Observaciones" maxlength="500"/></div>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ route('checks.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar cheque</button>
        </div>
    </form>
</x-layouts.app>
