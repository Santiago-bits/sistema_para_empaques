<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('locations.move');
    }

    public function rules(): array
    {
        return [
            'movable_type' => ['required', Rule::in(['pallet', 'crate'])],
            'movable_id' => ['required', 'integer', 'min:1'],
            'to_location_id' => ['nullable', 'required_without:to_label', 'integer', 'exists:warehouse_locations,id'],
            'to_label' => ['nullable', 'required_without:to_location_id', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['movable_id' => 'pallet o cajón', 'to_location_id' => 'ubicación de destino', 'to_label' => 'destino', 'notes' => 'observaciones'];
    }

    public function messages(): array
    {
        return [
            'movable_id.required' => 'Escaneá el pallet o cajón a mover.',
            'to_location_id.required_without' => 'Indicá la ubicación de destino.',
            'to_label.required_without' => 'Indicá la ubicación de destino.',
        ];
    }
}
