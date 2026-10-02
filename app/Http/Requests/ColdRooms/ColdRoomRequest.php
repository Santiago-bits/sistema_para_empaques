<?php

namespace App\Http\Requests\ColdRooms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ColdRoomRequest extends FormRequest
{
    private const DECIMALS = ['temp_min', 'temp_max', 'humidity_min', 'humidity_max'];

    public function authorize(): bool
    {
        return $this->user()->can('cold_rooms.manage');
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'code' => $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : null,
            'sensor_key' => $this->filled('sensor_key') ? trim((string) $this->input('sensor_key')) : null,
            'active' => $this->boolean('active'),
        ];
        foreach (self::DECIMALS as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = parse_number($this->input($field), false);
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $id = $this->route('coldRoom')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('cold_rooms', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'temp_min' => ['required', 'numeric', 'min:-40', 'max:40'],
            'temp_max' => ['required', 'numeric', 'min:-40', 'max:40', 'gt:temp_min'],
            'humidity_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'humidity_max' => ['nullable', 'numeric', 'min:0', 'max:100', 'gte:humidity_min'],
            'sensor_key' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('cold_rooms', 'sensor_key')->ignore($id)],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código', 'name' => 'nombre', 'location_id' => 'ubicación', 'temp_min' => 'temperatura mínima',
            'temp_max' => 'temperatura máxima', 'humidity_min' => 'humedad mínima', 'humidity_max' => 'humedad máxima',
            'sensor_key' => 'clave de sensor',
        ];
    }
}
