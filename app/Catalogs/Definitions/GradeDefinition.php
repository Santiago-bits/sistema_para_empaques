<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Model;

/** Selección / categoría comercial (Extra, Elegido, Comercial…): se imprime en la etiqueta. */
class GradeDefinition extends CatalogDefinition
{
    protected string $model = Grade::class;

    protected string $key = 'grades';

    protected string $uri = 'catalogos/selecciones';

    protected string $title = 'Selecciones';

    protected string $singular = 'selección';

    protected bool $feminine = true;

    protected string $icon = 'check-badge';

    protected string $group = 'Producción';

    protected string $description = 'Categoría comercial de la fruta (Extra, Elegido, Comercial).';

    protected array $searchable = ['code', 'name'];

    protected array $orderBy = ['sort_order' => 'asc', 'name' => 'asc'];

    protected array $inUseRelations = ['crates'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 20, 'class' => 'uppercase']),
            Field::text('name', 'Nombre')->required(),
            Field::number('sort_order', 'Orden')->hint('Orden en las listas (1 = primera).'),
            Field::checkbox('active', 'Activa'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('Orden', 'sort_order')->num(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        $input = $this->cleanUpper($input, 'code');
        $input['sort_order'] = ($input['sort_order'] ?? null) === null || $input['sort_order'] === '' ? 0 : $input['sort_order'];

        return $input;
    }

    public function importColumns(): array
    {
        return ['codigo' => 'code', 'nombre' => 'name', 'orden' => 'sort_order'];
    }

    public static function options(): array
    {
        return Grade::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }
}
