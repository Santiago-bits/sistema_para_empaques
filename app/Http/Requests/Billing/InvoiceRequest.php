<?php

namespace App\Http\Requests\Billing;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.manage');
    }

    protected function prepareForValidation(): void
    {
        $items = collect((array) $this->input('items', []))
            ->filter(fn ($i) => is_array($i) && trim((string) ($i['description'] ?? '')) !== '')
            ->map(function ($i) {
                foreach (['quantity', 'unit_price'] as $k) {
                    // Formato argentino: "1.234,56" → 1234.56; "18.5" se respeta.
                    if (isset($i[$k]) && is_string($i[$k]) && str_contains($i[$k], ',')) {
                        $i[$k] = str_replace(',', '.', str_replace('.', '', trim($i[$k])));
                    }
                }

                return $i;
            })->values()->all();
        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'load_id' => ['nullable', 'integer', Rule::exists('loads', 'id')],
            'voucher_type' => ['required', 'integer', Rule::in(array_keys(Invoice::VOUCHER_TYPES))],
            'issued_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.today()->subDays(5)->toDateString()],
            'currency' => ['required', Rule::in(['ARS', 'USD'])],
            'exchange_rate' => ['nullable', 'required_if:currency,USD', 'numeric', 'gt:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit' => ['nullable', 'string', 'max:10'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.vat_rate' => ['required', Rule::in(array_keys(InvoiceService::VAT_RATES))],
        ];
    }

    public function messages(): array
    {
        return [
            'issued_on.after_or_equal' => 'ARCA admite fechas de emisión de hasta 5 días atrás para venta de productos.',
            'items.required' => 'Agregá al menos un ítem.',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'cliente', 'voucher_type' => 'tipo de comprobante', 'issued_on' => 'fecha', 'currency' => 'moneda',
            'exchange_rate' => 'cotización', 'items.*.description' => 'descripción', 'items.*.quantity' => 'cantidad',
            'items.*.unit_price' => 'precio unitario', 'items.*.vat_rate' => 'alícuota de IVA',
        ];
    }
}
