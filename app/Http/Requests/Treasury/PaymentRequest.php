<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cobro o pago en cuenta corriente (efectivo, transferencia, cheque nuevo o endoso de uno en cartera). */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accounts.manage');
    }

    protected function prepareForValidation(): void
    {
        $check = (array) $this->input('check', []);
        $this->merge([
            'amount' => parse_number($this->input('amount')),
            'endorse_check_id' => $this->input('endorse_check_id') ?: null,
            'check' => $check + ['electronic' => false],
        ]);
    }

    public function rules(): array
    {
        $isCheck = $this->input('method') === 'check';
        $endorsing = $isCheck && $this->input('direction') === 'payment' && $this->filled('endorse_check_id');
        $newCheck = $isCheck && ! $endorsing;

        return [
            'direction' => ['required', Rule::in(['collection', 'payment'])],
            'type' => ['nullable', Rule::in(['payment', 'advance'])],
            'method' => ['required', Rule::in(['cash', 'transfer', 'check', 'other'])],
            'amount' => [Rule::requiredIf(! $endorsing), 'nullable', 'numeric', 'gt:0', 'max:999999999999'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:80'],
            'endorse_check_id' => ['nullable', 'integer', Rule::prohibitedIf($this->input('direction') !== 'payment'),
                Rule::exists('checks', 'id')->where('kind', 'third_party')->where('status', 'in_portfolio')],
            'check.bank' => [Rule::requiredIf($newCheck), 'nullable', 'string', 'max:80'],
            'check.number' => [Rule::requiredIf($newCheck), 'nullable', 'string', 'max:30'],
            'check.payment_date' => [Rule::requiredIf($newCheck), 'nullable', 'date'],
            'check.issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'check.electronic' => ['boolean'],
            'check.issuer_name' => ['nullable', 'string', 'max:120'],
            'check.issuer_cuit' => ['nullable', 'string', 'max:13'],
        ];
    }

    public function attributes(): array
    {
        return [
            'direction' => 'operación', 'method' => 'medio de pago', 'amount' => 'importe', 'date' => 'fecha',
            'description' => 'descripción', 'reference' => 'referencia', 'endorse_check_id' => 'cheque a endosar',
            'check.bank' => 'banco', 'check.number' => 'número de cheque', 'check.payment_date' => 'fecha de cobro',
            'check.issued_on' => 'fecha de emisión', 'check.issuer_name' => 'librador', 'check.issuer_cuit' => 'CUIT del librador',
        ];
    }

    public function messages(): array
    {
        return ['endorse_check_id.exists' => 'El cheque elegido ya no está en cartera.'];
    }
}
