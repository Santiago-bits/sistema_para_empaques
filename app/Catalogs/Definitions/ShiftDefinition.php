<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Model;

class ShiftDefinition extends CatalogDefinition
{
    protected string $model = Shift::class;

    protected string $key = 'shifts';

    protected string $uri = 'catalogos/turnos';

    protected string $title = 'Turnos';

    protected string $singular = 'turno';

    protected string $icon = 'clock';

    protected string $group = 'Producción';

    protected string $description = 'Turnos de trabajo (pueden cruzar la medianoche).';

    protected string $managePermission = 'shifts.manage';

    protected array $searchable = ['code', 'name'];

    protected array $orderBy = ['starts_at' => 'asc'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 20]),
            Field::text('name', 'Nombre')->required(),
            Field::time('starts_at', 'Hora de inicio')->required(),
            Field::time('ends_at', 'Hora de fin')->required()->hint('Si es menor que el inicio, el turno termina al día siguiente.'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('Horario', fn (Shift $s) => substr((string) $s->starts_at, 0, 5).' a '.substr((string) $s->ends_at, 0, 5)),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:60'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        $input = $this->cleanUpper($input, 'code');
        foreach (['starts_at', 'ends_at'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $input[$key])) {
                $input[$key] = substr($input[$key], 0, 5);
            }
        }

        return $input;
    }
}
