<?php

namespace App\Http\Requests\Locations;

use App\Models\WarehouseLocation;
use App\Support\CurrentWarehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('locations.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : null,
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        /** @var WarehouseLocation|null $location */
        $location = $this->route('location');
        $warehouseId = $location?->warehouse_id ?? CurrentWarehouse::id();

        return [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('warehouse_locations', 'id')->where('warehouse_id', $warehouseId),
                function (string $attribute, mixed $value, \Closure $fail) use ($location) {
                    if (! $location || ! $value) {
                        return;
                    }
                    // Evita ciclos: no puede depender de sí misma ni de una sububicación propia.
                    $node = WarehouseLocation::query()->find($value);
                    $guard = 0;
                    while ($node && $guard++ < 50) {
                        if ((int) $node->id === (int) $location->id) {
                            $fail('La ubicación padre no puede ser la misma ubicación ni una de sus sububicaciones.');

                            return;
                        }
                        $node = $node->parent_id ? WarehouseLocation::query()->find($node->parent_id) : null;
                    }
                },
            ],
            'type' => ['required', Rule::in(array_keys(WarehouseLocation::TYPES))],
            'code' => ['required', 'string', 'max:40', Rule::unique('warehouse_locations', 'code')->where('warehouse_id', $warehouseId)->ignore($location?->id)],
            'name' => ['required', 'string', 'max:100'],
            'capacity_pallets' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'map_x' => ['nullable', 'integer', 'min:1', 'max:200'],
            'map_y' => ['nullable', 'integer', 'min:1', 'max:200'],
            'map_w' => ['nullable', 'integer', 'min:1', 'max:200'],
            'map_h' => ['nullable', 'integer', 'min:1', 'max:200'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'parent_id' => 'ubicación padre', 'type' => 'tipo', 'code' => 'código', 'name' => 'nombre',
            'capacity_pallets' => 'capacidad (pallets)', 'map_x' => 'columna', 'map_y' => 'fila', 'map_w' => 'ancho', 'map_h' => 'alto',
        ];
    }
}
