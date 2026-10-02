<?php

namespace App\Http\Requests\Pallets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('pallet') ? 'pallets.update' : 'pallets.create');
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'barcode'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: null]);
            }
        }
        if (is_string($this->input('gross_weight'))) {
            $this->merge(['gross_weight' => parse_number($this->input('gross_weight'))]);
        }
    }

    public function rules(): array
    {
        $pallet = $this->route('pallet');

        return [
            'code' => $pallet ? ['prohibited'] : ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_.\/]+$/', Rule::unique('pallets', 'code')],
            'barcode' => ['nullable', 'string', 'max:60', Rule::unique('pallets', 'barcode')->ignore($pallet?->id)],
            'lot_id' => ['nullable', 'integer', $pallet
                ? Rule::exists('lots', 'id')->whereNull('deleted_at')
                : Rule::exists('lots', 'id')->whereNull('deleted_at')->where('status', 'open')],
            'producer_id' => ['nullable', 'required_without:lot_id', 'integer', Rule::exists('producers', 'id')->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('owners', 'id')->whereNull('deleted_at')],
            'variety_id' => ['nullable', 'integer', Rule::exists('varieties', 'id')],
            'origin' => ['nullable', 'string', 'max:255'],
            'received_at' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'gross_weight' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'location_id' => ['nullable', 'integer', Rule::exists('warehouse_locations', 'id')->where('active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'version' => $pallet ? ['required', 'integer'] : ['nullable'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código', 'barcode' => 'código de barras', 'lot_id' => 'lote', 'producer_id' => 'productor',
            'owner_id' => 'propietario', 'variety_id' => 'variedad', 'origin' => 'procedencia', 'received_at' => 'fecha de ingreso',
            'quantity' => 'cantidad', 'gross_weight' => 'peso bruto', 'location_id' => 'ubicación', 'notes' => 'observaciones',
        ];
    }

    public function messages(): array
    {
        return [
            'code.prohibited' => 'El código del pallet no se puede modificar.',
            'producer_id.required_without' => 'Indicá el productor o un lote.',
            'lot_id.exists' => 'El lote no existe o no está abierto.',
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);
        if (is_array($data) && array_key_exists('quantity', $data) && $data['quantity'] === null) {
            $data['quantity'] = 0;
        }

        return $data;
    }
}
