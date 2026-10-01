<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Reason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ReasonDefinition extends CatalogDefinition
{
    private const TYPE_COLORS = ['reject' => 'red', 'stoppage' => 'amber', 'incident' => 'violet'];

    protected string $model = Reason::class;

    protected string $key = 'reasons';

    protected string $uri = 'catalogos/motivos';

    protected string $title = 'Motivos';

    protected string $singular = 'motivo';

    protected string $icon = 'alert';

    protected string $group = 'Producción';

    protected string $description = 'Motivos configurables de rechazo, parada e incidente.';

    protected array $searchable = ['code', 'name'];

    protected array $orderBy = ['type' => 'asc', 'name' => 'asc'];

    public function fields(): array
    {
        return [
            Field::select('type', 'Tipo', Reason::TYPES)->required(),
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 30]),
            Field::text('name', 'Descripción')->required(),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Tipo', fn (Reason $r) => [Reason::TYPES[$r->type] ?? $r->type, self::TYPE_COLORS[$r->type] ?? 'stone'])->as('badge'),
            Column::make('Código', 'code')->code(),
            Column::make('Descripción', 'name')->strong(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('type', 'Tipo', Reason::TYPES)];
    }

    public function rules(?Model $record): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(Reason::TYPES))],
            'code' => ['required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('reasons', 'code')->where('type', (string) request()->input('type'))->ignore($record?->getKey())],
            'name' => ['required', 'string', 'max:100'],
            'active' => ['boolean'],
        ];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['type' => request()->query('type', 'reject')];
    }

    public function prepare(array $input): array
    {
        return $this->cleanUpper($input, 'code');
    }
}
