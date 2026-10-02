<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CodeSuggester;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class EmployeeDefinition extends CatalogDefinition
{
    protected string $model = Employee::class;

    protected string $key = 'employees';

    protected string $uri = 'catalogos/empleados';

    protected string $title = 'Empleados';

    protected string $singular = 'empleado';

    protected string $icon = 'user-chart';

    protected string $group = 'Personal';

    protected string $description = 'Personal del galpón, cuadrilla, jornal y cuenta corriente (adelantos, pagos).';

    protected string $viewPermission = 'staff.view';

    protected string $managePermission = 'staff.manage';

    protected array $searchable = ['code', 'first_name', 'last_name', 'dni', 'cuil'];

    protected array $orderBy = ['last_name' => 'asc', 'first_name' => 'asc'];

    protected array $with = ['crew'];

    public function recordLabel(Model $record): string
    {
        return $record->code.' — '.$record->full_name;
    }

    public function fields(): array
    {
        return [
            Field::text('code', 'Legajo')->required()->attrs(['maxlength' => 20, 'class' => 'uppercase']),
            Field::text('first_name', 'Nombre')->required(),
            Field::text('last_name', 'Apellido')->required(),
            Field::text('dni', 'DNI')->attrs(['inputmode' => 'numeric']),
            Field::text('cuil', 'CUIL')->attrs(['inputmode' => 'numeric'])->hint('11 dígitos, con o sin guiones.'),
            Field::text('phone', 'Teléfono'),
            Field::text('position', 'Puesto')->placeholder('Embalador, clasificador, autoelevadorista…'),
            Field::select('crew_id', 'Cuadrilla', fn () => CrewDefinition::options())->placeholder('Sin cuadrilla')
                ->display(fn (Model $r) => $r->crew?->name),
            Field::date('hired_on', 'Fecha de ingreso'),
            Field::number('daily_wage', 'Jornal ($ por día)', '0.01')->decimal()->display(fn (Model $r) => $r->daily_wage !== null ? money($r->daily_wage) : null),
            Field::textarea('notes', 'Observaciones'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Legajo', 'code')->code(),
            Column::make('Apellido y nombre', fn (Employee $e) => $e->full_name)->strong(),
            Column::make('DNI', 'dni'),
            Column::make('Puesto', 'position'),
            Column::make('Cuadrilla', 'crew.name'),
            Column::make('Jornal', fn (Employee $e) => $e->daily_wage !== null ? money($e->daily_wage) : null)->num(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('crew_id', 'Cuadrilla', fn () => CrewDefinition::options())];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', $this->unique('code', $record)],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'dni' => ['nullable', 'digits_between:7,9', $this->unique('dni', $record)],
            'cuil' => ['nullable', 'digits:11'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:80'],
            'crew_id' => ['nullable', 'integer', 'exists:crews,id'],
            'hired_on' => ['nullable', 'date'],
            'daily_wage' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['crew_id' => 'cuadrilla', 'hired_on' => 'fecha de ingreso', 'daily_wage' => 'jornal'];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['code' => CodeSuggester::next(Employee::class, 'EMP')];
    }

    public function prepare(array $input): array
    {
        $input = $this->cleanUpper($input, 'code');
        foreach (['dni', 'cuil'] as $key) {
            if (isset($input[$key]) && is_scalar($input[$key])) {
                $input[$key] = preg_replace('/\D/', '', (string) $input[$key]) ?: null;
            }
        }

        return $input;
    }
}
