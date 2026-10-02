<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Registro desde el modo escaneo (JSON). */
class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('production.scan');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['crate_code', 'packer_code', 'supervisor_login'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }
        if (isset($merge['packer_code'])) {
            $merge['packer_code'] = mb_strtoupper($merge['packer_code']);
        }
        if (is_string($this->input('weight'))) {
            $merge['weight'] = parse_number($this->input('weight'), false);
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'crate_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_.\/]+$/'],
            'packer_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:999.99'],
            'variety_id' => ['required', 'integer', Rule::exists('varieties', 'id')->where('active', true)],
            'size_id' => ['required', 'integer', Rule::exists('sizes', 'id')->where('active', true)],
            'production_line_id' => ['nullable', 'integer', Rule::exists('production_lines', 'id')->where('active', true)],
            'lot_id' => ['nullable', 'integer', Rule::exists('lots', 'id')->whereNull('deleted_at')],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'weight_source' => ['nullable', 'in:manual,scale'],
            'supervisor_login' => ['nullable', 'string', 'max:100'],
            'supervisor_password' => ['nullable', 'string', 'max:255'],
            'authorization_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['crate_code.regex' => 'El código del cajón tiene caracteres no válidos. Verificá la etiqueta.'];
    }

    public function attributes(): array
    {
        return [
            'crate_code' => 'cajón', 'packer_code' => 'embalador', 'weight' => 'peso', 'variety_id' => 'variedad',
            'size_id' => 'tamaño', 'production_line_id' => 'línea', 'lot_id' => 'lote',
            'supervisor_login' => 'usuario supervisor', 'authorization_reason' => 'motivo',
        ];
    }
}
