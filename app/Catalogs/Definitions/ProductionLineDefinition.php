<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\ProductionLine;
use Illuminate\Database\Eloquent\Model;

class ProductionLineDefinition extends CatalogDefinition
{
    protected string $model = ProductionLine::class;

    protected string $key = 'lines';

    protected string $uri = 'catalogos/lineas';

    protected string $title = 'Líneas de producción';

    protected string $singular = 'línea de producción';

    protected bool $feminine = true;

    protected string $icon = 'list';

    protected string $group = 'Producción';

    protected string $description = 'Líneas de empaque del galpón.';

    protected string $managePermission = 'shifts.manage';

    protected array $searchable = ['code', 'name'];

    protected array $orderBy = ['code' => 'asc'];

    protected array $with = ['warehouse'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 20]),
            Field::text('name', 'Nombre')->required(),
            Field::checkbox('active', 'Activa'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('Galpón', 'warehouse.name'),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:60'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        return $this->cleanUpper($input, 'code');
    }
}
