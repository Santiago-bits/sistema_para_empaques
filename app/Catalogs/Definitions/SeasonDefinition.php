<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Database\Eloquent\Model;

/** Temporadas: en lugar de activar/desactivar se marca la vigente (sólo una a la vez). */
class SeasonDefinition extends CatalogDefinition
{
    protected string $model = Season::class;

    protected string $key = 'seasons';

    protected string $uri = 'catalogos/temporadas';

    protected string $title = 'Temporadas';

    protected string $singular = 'temporada';

    protected bool $feminine = true;

    protected string $icon = 'clock';

    protected string $group = 'Producción';

    protected string $description = 'Temporadas de cosecha. La vigente se asigna por defecto a lotes, pallets y cajones.';

    protected array $searchable = ['name'];

    protected array $orderBy = ['starts_on' => 'desc'];

    public function fields(): array
    {
        return [
            Field::text('name', 'Nombre')->required()->hint('Ej.: Temporada 2026/27.'),
            Field::date('starts_on', 'Inicio')->required(),
            Field::date('ends_on', 'Fin')->required(),
            Field::checkbox('is_current', 'Temporada vigente')->hint('Al marcarla, deja de estar vigente la anterior.'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Nombre', 'name')->strong(),
            Column::make('Inicio', 'starts_on')->as('date'),
            Column::make('Fin', 'ends_on')->as('date'),
            Column::make('Estado', fn (Season $s) => $s->is_current ? ['Vigente', 'emerald'] : null)->as('badge'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:60', $this->unique('name', $record)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_current' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['starts_on' => 'inicio', 'ends_on' => 'fin'];
    }

    public function defaults(): array
    {
        return ['starts_on' => today()->startOfYear(), 'ends_on' => today()->endOfYear(), 'is_current' => false];
    }

    public function afterSave(Model $record, bool $created): void
    {
        if ($record->is_current) {
            app(SeasonService::class)->makeCurrent($record);
        }
    }

    public function hasToggle(): bool
    {
        return true;
    }

    public function toggleLabel(Model $record): string
    {
        return $record->is_current ? 'Quitar vigencia' : 'Marcar vigente';
    }

    public function toggle(Model $record): string
    {
        if ($record->is_current) {
            $record->update(['is_current' => false]);

            return 'La temporada ya no está vigente.';
        }

        app(SeasonService::class)->makeCurrent($record);

        return 'Temporada '.$record->name.' marcada como vigente.';
    }

    public function count(): int
    {
        return Season::query()->count();
    }
}
