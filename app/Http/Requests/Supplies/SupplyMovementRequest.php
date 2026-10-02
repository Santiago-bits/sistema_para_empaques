<?php

namespace App\Http\Requests\Supplies;

use App\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplyMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('supplies.manage');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['quantity', 'unit_cost'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = parse_number($this->input($field));
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(InventoryMovement::TYPES))],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo de movimiento', 'quantity' => 'cantidad', 'unit_cost' => 'costo unitario',
            'provider_id' => 'proveedor', 'reference' => 'referencia', 'notes' => 'observaciones',
        ];
    }
}
