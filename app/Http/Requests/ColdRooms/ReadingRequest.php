<?php

namespace App\Http\Requests\ColdRooms;

use Illuminate\Foundation\Http\FormRequest;

class ReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cold_rooms.manage');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['temperature', 'humidity'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = parse_number($this->input($field), false);
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'temperature' => ['required', 'numeric', 'min:-60', 'max:60'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function attributes(): array
    {
        return ['temperature' => 'temperatura', 'humidity' => 'humedad', 'recorded_at' => 'fecha y hora'];
    }
}
