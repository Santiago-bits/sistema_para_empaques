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
        // Formato argentino: "1.234,56" → 1234.56 y "125.000" → 125000.
        $this->merge(['amount' => parse_number($this->input('amount'))]);
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
