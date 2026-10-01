<?php

namespace App\Http\Requests\Catalogs;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/** Valida el alta/modificación de cualquier catálogo con las reglas de su definición. */
class CatalogRequest extends FormRequest
{
    private ?Model $resolvedRecord = null;

    public function definition(): CatalogDefinition
    {
        return CatalogRegistry::get((string) $this->route('catalog'));
    }

    public function record(): ?Model
    {
        $id = $this->route('record');
        if ($id === null) {
            return null;
        }

        return $this->resolvedRecord ??= $this->definition()->find($id);
    }

    public function authorize(): bool
    {
        return $this->user()->can($this->definition()->managePermission());
    }

    protected function prepareForValidation(): void
    {
        $fields = collect($this->definition()->fields())->pluck('name')->all();
        $input = [];
        foreach ($fields as $field) {
            $value = $this->input($field);
            $input[$field] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = mb_strtolower($input['email']);
        }

        $this->replace($this->definition()->prepare($input));
    }

    public function rules(): array
    {
        return $this->definition()->rules($this->record());
    }

    public function attributes(): array
    {
        return $this->definition()->attributes();
    }
}
