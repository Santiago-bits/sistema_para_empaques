<?php

namespace App\Http\Requests\Catalogs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lots.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : null,
        ]);
    }

    public function rules(): array
    {
        $lot = $this->route('lot');

        return [
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9\-\/_]+$/', Rule::unique('lots', 'code')->ignore($lot?->id)],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'producer_id' => ['required', 'integer', Rule::exists('producers', 'id')->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('owners', 'id')->whereNull('deleted_at')],
            'variety_id' => ['nullable', 'integer', 'exists:varieties,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'origin' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código', 'date' => 'fecha', 'producer_id' => 'productor', 'owner_id' => 'propietario',
            'variety_id' => 'variedad', 'season_id' => 'temporada', 'origin' => 'origen', 'field' => 'campo',
            'quantity' => 'cantidad', 'notes' => 'observaciones',
        ];
    }
}
