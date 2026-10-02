<?php

namespace App\Http\Requests\Supplies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplyRequest extends FormRequest
{
    private const DECIMALS = ['stock', 'min_stock', 'unit_cost'];

    public function authorize(): bool
    {
        return $this->user()->can('supplies.manage');
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'code' => $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : null,
            'active' => $this->boolean('active'),
        ];
        foreach (self::DECIMALS as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = parse_number($this->input($field));
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $supply = $this->route('supply');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('supplies', 'code')->ignore($supply?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:40'],
            'unit' => ['required', 'string', 'max:10'],
            // El stock inicial sólo se carga al crear; después cambia únicamente con movimientos.
            'stock' => $supply ? ['prohibited'] : ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código', 'name' => 'nombre', 'category' => 'categoría', 'unit' => 'unidad', 'stock' => 'stock inicial',
            'min_stock' => 'stock mínimo', 'unit_cost' => 'costo unitario', 'provider_id' => 'proveedor',
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);
        if ($key === null && is_array($data)) {
            $data['min_stock'] = $data['min_stock'] ?? 0;
        }

        return $data;
    }
}
