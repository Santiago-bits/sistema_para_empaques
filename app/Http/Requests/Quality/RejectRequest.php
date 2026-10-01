<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('weight'))) {
            $this->merge(['weight' => str_replace(',', '.', trim($this->input('weight')))]);
        }
    }

    public function rules(): array
    {
        return [
            'crate_code' => ['nullable', 'string', 'max:60'],
            'reason_id' => ['required', Rule::exists('reasons', 'id')->where('type', 'reject')->where('active', true)],
            'weight' => ['nullable', 'required_without:crate_code', 'numeric', 'min:0.01', 'max:99999'],
            'variety_id' => ['nullable', 'exists:varieties,id'],
            'size_id' => ['nullable', 'exists:sizes,id'],
            'lot_id' => ['nullable', 'exists:lots,id'],
            'packer_id' => ['nullable', 'exists:packers,id'],
            'rejected_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'crate_code' => 'código de cajón', 'reason_id' => 'motivo', 'weight' => 'peso', 'variety_id' => 'variedad',
            'size_id' => 'tamaño', 'lot_id' => 'lote', 'packer_id' => 'embalador', 'rejected_at' => 'fecha',
        ];
    }
}
