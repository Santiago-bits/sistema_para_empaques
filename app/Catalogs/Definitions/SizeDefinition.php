<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Size;
use Illuminate\Database\Eloquent\Model;

class SizeDefinition extends CatalogDefinition
{
    protected string $model = Size::class;

    protected string $key = 'sizes';

    protected string $uri = 'catalogos/tamanos';

    protected string $title = 'Tamaños';

    protected string $singular = 'tamaño';

    protected string $icon = 'scale';

    protected string $group = 'Producción';

    protected string $description = 'Calibres / tamaños, en el orden en que se muestran al operador.';

    protected array $searchable = ['code', 'name'];

    protected array $orderBy = ['sort' => 'asc', 'name' => 'asc'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 20]),
            Field::text('name', 'Nombre')->required(),
            Field::number('sort', 'Orden')->hint('Menor número = aparece primero.'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Orden', 'sort')->num(),
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:60'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'active' => ['boolean'],
        ];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['sort' => ((int) Size::query()->max('sort')) + 10];
    }

    public function prepare(array $input): array
    {
        $input = $this->cleanUpper($input, 'code');
        if (array_key_exists('sort', $input) && ($input['sort'] === null || $input['sort'] === '')) {
            $input['sort'] = 0;
        }

        return $input;
    }

    public function importColumns(): array
    {
        return ['codigo' => 'code', 'nombre' => 'name', 'orden' => 'sort'];
    }
}
