<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CodeSuggester;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Packer;
use App\Models\Shift;
use App\Services\PackerService;
use Illuminate\Database\Eloquent\Model;

/** Embaladores: usan las vistas genéricas + ficha y credencial propias (PackerController). */
class PackerDefinition extends CatalogDefinition
{
    public const CODE_PREFIX = 'EMB';

    protected string $model = Packer::class;

    protected string $key = 'packers';

    protected string $uri = 'catalogos/embaladores';

    protected string $routePrefix = '';

    protected string $title = 'Embaladores';

    protected string $singular = 'embalador';

    protected string $icon = 'users';

    protected string $group = 'Producción';

    protected string $description = 'Personal de empaque, credenciales con código de barras y producción.';

    protected string $viewPermission = 'packers.view';

    protected string $managePermission = 'packers.manage';

    protected array $searchable = ['code', 'first_name', 'last_name', 'dni'];

    protected array $orderBy = ['last_name' => 'asc', 'first_name' => 'asc'];

    protected array $with = ['shift'];

    protected array $inUseRelations = ['productionRecords', 'crates'];

    public function recordLabel(Model $record): string
    {
        return $record->code.' — '.$record->full_name;
    }

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->hint('Es el código que escanea el operador. Se sugiere el próximo libre.')
                ->attrs(['maxlength' => 30, 'class' => 'uppercase', 'autocomplete' => 'off']),
            Field::text('first_name', 'Nombre')->required(),
            Field::text('last_name', 'Apellido')->required(),
            Field::text('dni', 'DNI')->attrs(['inputmode' => 'numeric']),
            Field::select('shift_id', 'Turno habitual', fn () => self::shiftOptions())->placeholder('Sin turno fijo')
                ->display(fn (Model $r) => $r->shift?->name),
            Field::date('hired_on', 'Fecha de ingreso'),
            Field::textarea('notes', 'Observaciones'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Apellido y nombre', fn (Packer $p) => $p->last_name.', '.$p->first_name)->strong(),
            Column::make('DNI', 'dni'),
            Column::make('Turno', 'shift.name'),
            Column::make('Ingreso', 'hired_on')->as('date'),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('shift_id', 'Turno', fn () => self::shiftOptions())];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/', $this->unique('code', $record)],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'dni' => ['nullable', 'digits_between:7,9', $this->unique('dni', $record)],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'hired_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['shift_id' => 'turno', 'hired_on' => 'fecha de ingreso'];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['code' => CodeSuggester::next(Packer::class, self::CODE_PREFIX)];
    }

    public function prepare(array $input): array
    {
        $input = $this->cleanUpper($input, 'code');
        if (isset($input['dni']) && is_scalar($input['dni'])) {
            $input['dni'] = preg_replace('/\D/', '', (string) $input['dni']) ?: null;
        }

        return $input;
    }

    public function showView(): string
    {
        return 'packers.show';
    }

    public function showData(Model $record): array
    {
        return ['summary' => app(PackerService::class)->productionSummary($record)];
    }

    public function headerActions(): array
    {
        return [[
            'label' => 'Credenciales de todos', 'url' => route('packers.badges'), 'icon' => 'printer', 'permission' => 'packers.view',
        ]];
    }

    public function bulkAction(): ?array
    {
        return ['label' => 'Imprimir credenciales', 'route' => 'packers.badges', 'icon' => 'printer'];
    }

    public function importColumns(): array
    {
        return [
            'codigo' => 'code', 'nombre' => 'first_name', 'apellido' => 'last_name', 'dni' => 'dni',
            'turno' => 'shift_id', 'fecha_ingreso' => 'hired_on',
        ];
    }

    public function importKeys(): array
    {
        return ['code', 'dni'];
    }

    /** En la planilla el turno se indica por código (M, T, N) o nombre. */
    public function prepareImport(array $row): array
    {
        $shift = trim((string) ($row['shift_id'] ?? ''));
        if ($shift !== '') {
            $row['shift_id'] = Shift::query()->where('code', $shift)->orWhere('name', $shift)->value('id') ?? 'invalid';
        } else {
            $row['shift_id'] = null;
        }

        return $row;
    }

    public static function shiftOptions(): array
    {
        return Shift::query()->where('active', true)->orderBy('starts_at')->pluck('name', 'id')->all();
    }
}
