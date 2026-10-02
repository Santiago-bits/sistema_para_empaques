<?php

namespace App\Http\Requests\Quality;

use App\Models\QualityControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QualityControlRequest extends FormRequest
{
    private const DECIMALS = ['damage_pct', 'bruise_pct', 'rot_pct', 'reject_pct', 'reject_weight'];

    public function authorize(): bool
    {
        return $this->user()->can('quality.manage');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (self::DECIMALS as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = parse_number($this->input($field), $field === 'reject_weight');
            }
        }
        $merge['register_reject'] = $this->boolean('register_reject');
        $merge['apply_to_crates'] = $this->boolean('apply_to_crates');
        $this->merge($merge);
    }

    public function rules(): array
    {
        $pct = ['nullable', 'numeric', 'min:0', 'max:100'];

        return [
            'target_type' => ['required', Rule::in(['crate', 'lot', 'pallet'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'result' => ['required', Rule::in(array_keys(QualityControl::RESULTS))],
            'grade' => ['nullable', 'string', 'max:20'],
            'caliber' => ['nullable', 'string', 'max:20'],
            'ripeness' => ['nullable', 'string', 'max:30'],
            'damage_pct' => $pct,
            'bruise_pct' => $pct,
            'rot_pct' => $pct,
            'reject_pct' => $pct,
            'defects' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'controlled_at' => ['nullable', 'date', 'before_or_equal:now'],
            'register_reject' => ['boolean'],
            'reason_id' => ['nullable', 'required_if:register_reject,true', Rule::exists('reasons', 'id')->where('type', 'reject')],
            'reject_weight' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'apply_to_crates' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'target_id' => 'código', 'result' => 'resultado', 'reason_id' => 'motivo de rechazo',
            'reject_weight' => 'peso rechazado', 'damage_pct' => '% daños', 'bruise_pct' => '% golpes',
            'rot_pct' => '% podredumbre', 'reject_pct' => '% rechazo', 'controlled_at' => 'fecha y hora',
        ];
    }

    public function messages(): array
    {
        return [
            'target_id.required' => 'Escaneá o ingresá el código del cajón, lote o pallet.',
            'reason_id.required_if' => 'Seleccioná el motivo del rechazo.',
        ];
    }
}
