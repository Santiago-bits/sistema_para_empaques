<x-layouts.app :title="'Corregir cheque '.$check->number">
    <x-page-header :title="'Corregir cheque '.$check->bank.' N° '.$check->number" :subtitle="\App\Models\Check::KINDS[$check->kind].' · '.$check->statusLabel()"
                   :back="route('checks.show', $check)"/>

    <form method="POST" action="{{ route('checks.update', $check) }}" class="max-w-4xl space-y-6">
        @csrf @method('PUT')
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="bank" label="Banco" :value="$check->bank" required maxlength="80"/>
                <x-input name="number" label="Número" :value="$check->number" required maxlength="30"/>
                <x-input name="amount" inputmode="decimal" label="Importe" :value="num($check->amount, 2)" required
                         :hint="in_array($check->status, ['in_portfolio', 'issued'], true) ? 'Si cambia, el cobro/pago de la cuenta corriente se corrige solo.' : 'Con el cheque '.mb_strtolower($check->statusLabel()).' el importe ya no se puede cambiar.'"/>
                <x-input name="issued_on" type="date" label="Fecha de emisión" :value="$check->issued_on" required/>
                <x-input name="payment_date" type="date" label="Fecha de cobro" :value="$check->payment_date" required/>
                <x-checkbox name="electronic" label="E-cheq (electrónico)" :checked="$check->electronic"/>
                <x-input name="issuer_name" label="Librador" :value="$check->issuer_name" maxlength="120"/>
                <x-input name="issuer_cuit" label="CUIT del librador" :value="$check->issuer_cuit" maxlength="13"/>
            </div>
            <div class="mt-4"><x-textarea name="notes" label="Observaciones" :value="$check->notes"/></div>
        </x-panel>
        <x-correction-reason/>
        <div class="flex justify-end gap-2">
            <a href="{{ route('checks.show', $check) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar corrección</button>
        </div>
    </form>
</x-layouts.app>
