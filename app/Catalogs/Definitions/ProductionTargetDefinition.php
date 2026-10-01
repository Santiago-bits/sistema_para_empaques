<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\ProductionLine;
use App\Models\ProductionTarget;
use App\Models\Shift;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ProductionTargetDefinition extends CatalogDefinition
{
    public const PERIODS = ['daily' => 'Diario', 'weekly' => 'Semanal', 'monthly' => 'Mensual'];

    protected string $model = ProductionTarget::class;

    protected string $key = 'targets';

    protected string $uri = 'catalogos/objetivos';

    protected string $title = 'Objetivos de producción';

    protected string $singular = 'objetivo';

    protected string $icon = 'chart-line';

    protected string $group = 'Producción';

    protected string $description = 'Metas de kg y cajones por período, línea y turno.';

    protected string $managePermission = 'shifts.manage';

    protected array $searchable = [];

    protected array $orderBy = ['period' => 'asc', 'production_line_id' => 'asc', 'shift_id' => 'asc'];

    protected array $with = ['productionLine', 'shift'];

    public function recordLabel(Model $record): string
    {
        return collect([
            self::PERIODS[$record->period] ?? $record->period,
            $record->productionLine?->name ?? 'Todas las líneas',
            $record->shift?->name ?? 'Todos los turnos',
        ])->join(' · ');
    }

    public function fields(): array
    {
        return [
            Field::select('period', 'Período', self::PERIODS)->required(),
            Field::select('production_line_id', 'Línea', fn () => ProductionLine::query()->orderBy('code')->pluck('name', 'id')->all())
                ->placeholder('Todas las líneas')->display(fn (Model $r) => $r->productionLine?->name ?? 'Todas las líneas'),
            Field::select('shift_id', 'Turno', fn () => Shift::query()->orderBy('starts_at')->pluck('name', 'id')->all())
                ->placeholder('Todos los turnos')->display(fn (Model $r) => $r->shift?->name ?? 'Todos los turnos'),
            Field::number('target_kg', 'Objetivo (kg)', '0.01')->required()->display(fn (Model $r) => kg($r->target_kg)),
            Field::number('target_crates', 'Objetivo (cajones)')->required(),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Período', fn (ProductionTarget $t) => [self::PERIODS[$t->period] ?? $t->period, 'sky'])->as('badge'),
            Column::make('Línea', fn (ProductionTarget $t) => $t->productionLine?->name ?? 'Todas'),
            Column::make('Turno', fn (ProductionTarget $t) => $t->shift?->name ?? 'Todos'),
            Column::make('Objetivo kg', fn (ProductionTarget $t) => num($t->target_kg, 2))->num(),
            Column::make('Objetivo cajones', fn (ProductionTarget $t) => num($t->target_crates))->num(),
        ];
    }

    public function filters(): array
    {
        return [Filter::equals('period', 'Período', self::PERIODS)];
    }

    public function rules(?Model $record): array
    {
        return [
            'period' => ['required', Rule::in(array_keys(self::PERIODS)), $this->uniqueCombination($record)],
            'production_line_id' => ['nullable', 'integer', 'exists:production_lines,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'target_kg' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'target_crates' => ['required', 'integer', 'min:0', 'max:4000000000'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['production_line_id' => 'línea', 'shift_id' => 'turno'];
    }

    public function defaults(): array
    {
        return ['period' => 'daily', 'target_kg' => 0, 'target_crates' => 0];
    }

    /** Un solo objetivo por período + línea + turno (NULL = "todos", comparado como igual). */
    private function uniqueCombination(?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record) {
            $line = request()->input('production_line_id') ?: null;
            $shift = request()->input('shift_id') ?: null;
            $exists = ProductionTarget::query()
                ->where('period', $value)
                ->when($line, fn ($q) => $q->where('production_line_id', $line), fn ($q) => $q->whereNull('production_line_id'))
                ->when($shift, fn ($q) => $q->where('shift_id', $shift), fn ($q) => $q->whereNull('shift_id'))
                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                ->exists();
            if ($exists) {
                $fail('Ya existe un objetivo para ese período, línea y turno.');
            }
        };
    }

    /** Configuración sin referencias: se puede eliminar (queda registrado en auditoría). */
    public function canDelete(): bool
    {
        return true;
    }

    public function hasShow(): bool
    {
        return false;
    }

    public function count(): int
    {
        return ProductionTarget::query()->count();
    }
}
