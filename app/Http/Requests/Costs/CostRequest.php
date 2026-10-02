<?php

namespace App\Http\Requests\Costs;

use App\Models\Cost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('costs.manage');
    }

    protected function prepareForValidation(): void
    {
        $amount = is_string($this->input('amount')) ? trim(str_replace(['$', ' '], '', $this->input('amount'))) : $this->input('amount');
        // Formato argentino: "1.234,56" → 1234.56 y "125.000" → 125000 (puntos de miles); "18.5" se respeta.
        if (is_string($amount) && (str_contains($amount, ',') || preg_match('/^\d{1,3}(\.\d{3})+$/', $amount))) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        }
        $this->merge(['amount' => $amount]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(Cost::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'load_id' => ['nullable', 'integer', 'exists:loads,id'],
        ];
    }

    public function attributes(): array
    {
        return ['category' => 'categoría', 'description' => 'descripción', 'amount' => 'importe', 'date' => 'fecha', 'load_id' => 'carga'];
    }
}
