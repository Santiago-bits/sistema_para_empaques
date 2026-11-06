<?php

namespace App\Http\Requests\Catalogs;

use App\Models\ContainerMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContainerMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('containers.manage');
    }

    /** Acepta el valor como se escribe en Argentina: «2.500», «2500,50». */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_price' => parse_number($this->input('unit_price')),
            'container_type_id' => $this->input('container_type_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(array_keys(ContainerMovement::DIRECTIONS))],
            'container_type_id' => ['nullable', 'integer', Rule::exists('container_types', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', Rule::in(array_keys(ContainerMovement::REASONS))],
            'unit_price' => ['nullable', 'required_if:reason,payment', 'numeric', 'min:0.01', 'max:99999999'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'direction' => 'movimiento', 'container_type_id' => 'tipo de envase', 'quantity' => 'cantidad',
            'reason' => 'motivo', 'unit_price' => 'valor de cada envase', 'date' => 'fecha', 'description' => 'detalle',
        ];
    }

    public function messages(): array
    {
        return ['unit_price.required_if' => 'Indicá cuánto vale cada envase para descontarlo de la cuenta.'];
    }
}
