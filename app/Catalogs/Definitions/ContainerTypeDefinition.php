<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\ContainerType;
use Illuminate\Database\Eloquent\Model;

class ContainerTypeDefinition extends CatalogDefinition
{
    protected string $model = ContainerType::class;

    protected string $key = 'containers';

    protected string $uri = 'catalogos/envases';

    protected string $title = 'Tipos de envase';

    protected string $singular = 'envase';

    protected string $icon = 'archive';

    protected string $group = 'Producción';

    protected string $description = 'Bins, cajas y jaulas: tara y capacidad.';

    protected array $searchable = ['code', 'name'];

    protected array $inUseRelations = ['crates', 'lots'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 20, 'class' => 'uppercase']),
            Field::text('name', 'Nombre')->required()->placeholder('Caja de cartón 18 kg'),
            Field::select('kind', 'Tipo', ContainerType::KINDS)->required(),
            Field::number('tare_kg', 'Tara (kg)', '0.01')->decimal(),
            Field::number('capacity_kg', 'Capacidad (kg)', '0.01')->decimal(),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('Tipo', fn (ContainerType $c) => ContainerType::KINDS[$c->kind] ?? $c->kind),
            Column::make('Tara', fn (ContainerType $c) => $c->tare_kg !== null ? kg($c->tare_kg) : null)->num(),
            Column::make('Capacidad', fn (ContainerType $c) => $c->capacity_kg !== null ? kg($c->capacity_kg) : null)->num(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('kind', 'Tipo', ContainerType::KINDS)];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', 'in:'.implode(',', array_keys(ContainerType::KINDS))],
            'tare_kg' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'active' => ['boolean'],
        ];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['kind' => 'box'];
    }

    public function prepare(array $input): array
    {
        return $this->cleanUpper($input, 'code');
    }

    public function importColumns(): array
    {
        return ['codigo' => 'code', 'nombre' => 'name', 'tipo' => 'kind', 'tara_kg' => 'tare_kg', 'capacidad_kg' => 'capacity_kg'];
    }

    public function prepareImport(array $row): array
    {
        if (isset($row['kind']) && ! array_key_exists($row['kind'], ContainerType::KINDS)) {
            $row['kind'] = array_search(mb_strtolower(trim((string) $row['kind'])), array_map('mb_strtolower', ContainerType::KINDS), true) ?: $row['kind'];
        }
        foreach (['tare_kg', 'capacity_kg'] as $key) {
            if (isset($row[$key])) {
                $row[$key] = parse_number($row[$key], false);
            }
        }

        return $row;
    }

    public function exportValue(Model $record, string $field): mixed
    {
        return $field === 'kind' ? (ContainerType::KINDS[$record->kind] ?? $record->kind) : parent::exportValue($record, $field);
    }

    public static function options(): array
    {
        return ContainerType::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
    }
}
