<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CodeSuggester;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Crew;
use Illuminate\Database\Eloquent\Model;

class CrewDefinition extends CatalogDefinition
{
    protected string $model = Crew::class;

    protected string $key = 'crews';

    protected string $uri = 'catalogos/cuadrillas';

    protected string $title = 'Cuadrillas';

    protected string $singular = 'cuadrilla';

    protected bool $feminine = true;

    protected string $icon = 'users';

    protected string $group = 'Personal';

    protected string $description = 'Cuadrillas de cosecha, empaque y carga con su encargado.';

    protected string $viewPermission = 'staff.view';

    protected string $managePermission = 'staff.manage';

    protected array $searchable = ['code', 'name', 'leader'];

    protected array $inUseRelations = ['employees'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 20, 'class' => 'uppercase']),
            Field::text('name', 'Nombre')->required(),
            Field::select('kind', 'Tipo', Crew::KINDS)->required(),
            Field::text('leader', 'Encargado / capataz'),
            Field::checkbox('active', 'Activa'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('Tipo', fn (Crew $c) => Crew::KINDS[$c->kind] ?? $c->kind),
            Column::make('Encargado', 'leader'),
            Column::make('Personas', fn (Crew $c) => $c->employees()->where('active', true)->count())->num(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('kind', 'Tipo', Crew::KINDS)];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', 'in:'.implode(',', array_keys(Crew::KINDS))],
            'leader' => ['nullable', 'string', 'max:120'],
            'active' => ['boolean'],
        ];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['kind' => 'packing', 'code' => CodeSuggester::next(Crew::class, 'CUA')];
    }

    public function prepare(array $input): array
    {
        return $this->cleanUpper($input, 'code');
    }

    public static function options(): array
    {
        return Crew::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
    }
}
