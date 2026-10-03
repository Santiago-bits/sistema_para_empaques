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
            'kg_received' => parse_number($this->input('kg_received')),
            'price_per_kg' => parse_number($this->input('price_per_kg')),
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
            'bins' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'dtv_number' => ['nullable', 'string', 'max:30'],
            'container_type_id' => ['nullable', 'integer', Rule::exists('container_types', 'id')->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:255'],
            'kg_received' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'price_per_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código', 'date' => 'fecha', 'producer_id' => 'productor', 'owner_id' => 'propietario',
            'variety_id' => 'variedad', 'season_id' => 'temporada', 'origin' => 'origen', 'field' => 'campo',
            'quantity' => 'cantidad', 'bins' => 'bines', 'driver_id' => 'chofer', 'dtv_number' => 'n° de DTV', 'notes' => 'observaciones', 'container_type_id' => 'envase',
            'kg_received' => 'kilos recibidos', 'price_per_kg' => 'precio por kilo',
        ];
    }
}
