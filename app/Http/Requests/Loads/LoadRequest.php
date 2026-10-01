<?php

namespace App\Http\Requests\Loads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('load') ? 'loads.update' : 'loads.create');
    }

    public function rules(): array
    {
        $active = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');

        return [
            'date' => ['required', 'date'],
            'truck_id' => ['nullable', 'integer', $active('trucks')],
            'driver_id' => ['nullable', 'integer', $active('drivers')],
            'transporter_id' => ['nullable', 'integer', $active('transporters')],
            'destination_id' => ['nullable', 'integer', $active('destinations')],
            'client_id' => ['nullable', 'integer', $active('clients')],
            'owner_id' => ['nullable', 'integer', $active('owners')],
            'planned_crates' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('client_id') || ! $this->filled('destination_id')) {
                return;
            }
            $owner = \App\Models\Destination::query()->whereKey($this->integer('destination_id'))->value('client_id');
            if ($owner !== null && (int) $owner !== $this->integer('client_id')) {
                $validator->errors()->add('destination_id', 'El destino elegido pertenece a otro cliente.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'date' => 'fecha', 'truck_id' => 'camión', 'driver_id' => 'chofer', 'transporter_id' => 'transportista',
            'destination_id' => 'destino', 'client_id' => 'cliente', 'owner_id' => 'propietario', 'planned_crates' => 'cajones previstos',
        ];
    }
}
