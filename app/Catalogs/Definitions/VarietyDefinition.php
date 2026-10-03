<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Variety;
use Illuminate\Database\Eloquent\Model;

class VarietyDefinition extends CatalogDefinition
{
    protected string $model = Variety::class;

    protected string $key = 'varieties';

    protected string $uri = 'catalogos/variedades';

    protected string $title = 'Productos (variedades de fruta)';

    protected string $singular = 'producto';

    protected bool $feminine = false;

    protected string $icon = 'layers';

    protected string $group = 'Producción';

    protected string $description = 'Las frutas que se empacan (naranja Valencia, limón Eureka…), con su color.';

    protected array $searchable = ['code', 'name', 'species'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 20]),
            Field::text('name', 'Nombre')->required(),
            Field::text('species', 'Especie')->hint('Ej.: Pera, Manzana, Cereza.'),
            Field::color('color', 'Color')->hint('Se usa en etiquetas, gráficos y el mapa del galpón.'),
            Field::checkbox('active', 'Activa'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('', 'color')->as('color'),
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('Especie', 'species'),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:80'],
            'species' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'regex:/^#[0-9a-f]{6}$/'],
            'active' => ['boolean'],
        ];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['color' => '#16a34a'];
    }

    public function prepare(array $input): array
    {
        $input = $this->cleanUpper($input, 'code');
        if (isset($input['color']) && is_string($input['color'])) {
            $color = strtolower(trim($input['color']));
            $input['color'] = $color === '' ? null : (str_starts_with($color, '#') ? $color : '#'.$color);
        }

        return $input;
    }

    public function importColumns(): array
    {
        return ['codigo' => 'code', 'nombre' => 'name', 'especie' => 'species', 'color' => 'color'];
    }
}
