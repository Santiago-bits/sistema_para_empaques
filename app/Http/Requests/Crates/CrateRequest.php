<?php

namespace App\Http\Requests\Crates;

use App\Services\CrateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta / edición de cajón. La obligatoriedad de cada campo sale de la
 * configuración `fields.crate` (obligatorio / opcional / oculto).
 */
class CrateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('crate') ? 'crates.update' : 'crates.create');
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'barcode', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: null]);
            }
        }
        if (is_string($this->input('weight'))) {
            $this->merge(['weight' => parse_number($this->input('weight'), false)]);
        }
    }

    public function rules(): array
    {
        $crate = $this->route('crate');
        $service = app(CrateService::class);
        $mode = fn (string $field) => $service->fieldMode($field) === 'required' ? 'required' : 'nullable';

        $rules = [
            'code' => $crate ? ['prohibited'] : ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_.\/]+$/', Rule::unique('crates', 'code')],
            'barcode' => [$mode('barcode'), 'string', 'max:60', Rule::unique('crates', 'barcode')->ignore($crate?->id)],
            'pallet_id' => [$mode('pallet_id'), 'integer', Rule::exists('pallets', 'id')->whereNull('deleted_at')->whereNot('status', 'voided')],
            'lot_id' => [$mode('lot_id'), 'integer', Rule::exists('lots', 'id')->whereNull('deleted_at')],
            'producer_id' => ['nullable', 'integer', Rule::exists('producers', 'id')->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('owners', 'id')->whereNull('deleted_at')],
            'variety_id' => [$mode('variety_id'), 'integer', Rule::exists('varieties', 'id')],
            'size_id' => [$mode('size_id'), 'integer', Rule::exists('sizes', 'id')],
            'packer_id' => [$mode('packer_id'), 'integer', Rule::exists('packers', 'id')->whereNull('deleted_at')],
            'weight' => [$mode('weight'), 'numeric', 'gt:0', 'max:999.99'],
            'location_id' => [$mode('location_id'), 'integer', Rule::exists('warehouse_locations', 'id')],
            'notes' => [$mode('notes'), 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:255'],
            'version' => $crate ? ['required', 'integer'] : ['nullable'],
            'process' => $crate ? ['prohibited'] : ['nullable', 'boolean'],
        ];

        // Campos ocultos: no se aceptan desde el formulario.
        foreach ($service->fieldModes() as $field => $fieldMode) {
            if ($fieldMode === 'hidden') {
                $rules[$field] = ['nullable', 'prohibited'];
            }
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('process')) {
                return;
            }
            foreach (['packer_id' => 'embalador', 'variety_id' => 'variedad', 'size_id' => 'tamaño', 'weight' => 'peso'] as $field => $label) {
                if (! $this->filled($field)) {
                    $validator->errors()->add($field, "Para registrar la producción indicá {$label}.");
                }
            }
        });
    }

    public function attributes(): array
    {
        return array_merge(array_map('mb_strtolower', CrateService::CONFIGURABLE_FIELDS), [
            'code' => 'código', 'producer_id' => 'productor', 'owner_id' => 'propietario', 'reason' => 'motivo',
        ]);
    }

    public function messages(): array
    {
        return ['code.prohibited' => 'El código del cajón no se puede modificar.'];
    }
}
