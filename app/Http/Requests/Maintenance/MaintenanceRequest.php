<?php

namespace App\Http\Requests\Maintenance;

use App\Models\Machine;
use App\Models\Maintenance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('maintenance.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cost'))) {
            // Formato argentino: 12.500,50 → 12500.50
            $cost = trim($this->input('cost'));
            if (str_contains($cost, ',')) {
                $cost = str_replace(['.', ','], ['', '.'], $cost);
            }
            $this->merge(['cost' => $cost]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(Maintenance::TYPES))],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'technician' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'parts_used' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_maintenance_on' => ['nullable', 'date', 'after_or_equal:date'],
            'machine_status' => ['nullable', Rule::in(array_keys(Machine::STATUSES))],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo', 'date' => 'fecha', 'technician' => 'técnico', 'cost' => 'costo', 'parts_used' => 'repuestos',
            'notes' => 'observaciones', 'next_maintenance_on' => 'próximo mantenimiento', 'machine_status' => 'estado de la máquina',
        ];
    }
}
