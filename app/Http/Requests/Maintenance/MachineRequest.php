<?php

namespace App\Http\Requests\Maintenance;

use App\Models\Machine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('maintenance.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : null]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('machines', 'code')->ignore($this->route('machine')?->id)],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'status' => ['required', Rule::in(array_keys(Machine::STATUSES))],
            'next_maintenance_on' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código', 'name' => 'nombre', 'brand' => 'marca', 'model' => 'modelo', 'serial_number' => 'número de serie',
            'location_id' => 'ubicación', 'status' => 'estado', 'next_maintenance_on' => 'próximo mantenimiento',
        ];
    }
}
