<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DriverDefinition extends CatalogDefinition
{
    /** Días de anticipación con que se resalta el vencimiento de la licencia. */
    public const LICENSE_WARNING_DAYS = 15;

    protected string $model = Driver::class;

    protected string $key = 'drivers';

    protected string $uri = 'catalogos/choferes';

    protected string $title = 'Choferes';

    protected string $singular = 'chofer';

    protected string $icon = 'user-chart';

    protected string $group = 'Logística';

    protected string $description = 'Choferes y vencimiento de licencias.';

    protected array $searchable = ['first_name', 'last_name', 'dni', 'license_number'];

    protected array $orderBy = ['last_name' => 'asc', 'first_name' => 'asc'];

    protected array $with = ['transporter'];

    protected array $inUseRelations = ['loads'];

    public function recordLabel(Model $record): string
    {
        return (string) $record->full_name;
    }

    public function fields(): array
    {
        return [
            Field::text('first_name', 'Nombre')->required(),
            Field::text('last_name', 'Apellido')->required(),
            Field::text('dni', 'DNI')->required()->attrs(['inputmode' => 'numeric']),
            Field::text('cuil', 'CUIL')->attrs(['inputmode' => 'numeric'])->hint('11 dígitos, con o sin guiones.'),
            Field::date('birth_date', 'Fecha de nacimiento'),
            Field::text('phone', 'Teléfono'),
            Field::email('email', 'Email'),
            Field::text('address', 'Dirección'),
            Field::text('locality', 'Localidad'),
            Field::text('license_number', 'N° de licencia'),
            Field::text('license_category', 'Categoría de licencia')->placeholder('E1, E2…'),
            Field::date('license_expires_on', 'Vencimiento de licencia'),
            Field::select('transporter_id', 'Transportista', fn () => TruckDefinition::transporterOptions())->placeholder('Sin transportista')
                ->display(fn (Model $r) => $r->transporter?->business_name),
            Field::text('emergency_contact', 'Contacto de emergencia'),
            Field::text('emergency_phone', 'Teléfono de emergencia'),
            Field::textarea('notes', 'Observaciones'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Apellido y nombre', fn (Driver $d) => $d->last_name.', '.$d->first_name)->strong(),
            Column::make('DNI', 'dni')->code(),
            Column::make('Licencia', 'license_number'),
            Column::make('Vencimiento', 'license_expires_on')->as('license'),
            Column::make('Transportista', 'transporter.business_name'),
            Column::make('Teléfono', 'phone'),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [
            ...parent::filters(),
            Filter::equals('transporter_id', 'Transportista', fn () => TruckDefinition::transporterOptions()),
            new Filter('license', 'Licencia', ['expired' => 'Vencida', 'soon' => 'Vence en '.self::LICENSE_WARNING_DAYS.' días', 'ok' => 'Vigente'],
                function (Builder $q, string $value) {
                    $today = today();
                    $limit = today()->addDays(self::LICENSE_WARNING_DAYS);
                    match ($value) {
                        'expired' => $q->whereDate('license_expires_on', '<', $today),
                        'soon' => $q->whereDate('license_expires_on', '>=', $today)->whereDate('license_expires_on', '<=', $limit),
                        'ok' => $q->whereDate('license_expires_on', '>', $limit),
                        default => null,
                    };
                }),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'dni' => ['required', 'digits_between:7,9', $this->unique('dni', $record)],
            'cuil' => ['nullable', 'digits:11'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'locality' => ['nullable', 'string', 'max:120'],
            'license_number' => ['nullable', 'string', 'max:40'],
            'license_category' => ['nullable', 'string', 'max:20'],
            'license_expires_on' => ['nullable', 'date'],
            'transporter_id' => ['nullable', 'integer', 'exists:transporters,id'],
            'emergency_contact' => ['nullable', 'string', 'max:120'],
            'emergency_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        foreach (['dni', 'cuil'] as $key) {
            if (isset($input[$key]) && is_scalar($input[$key])) {
                $input[$key] = preg_replace('/\D/', '', (string) $input[$key]) ?: null;
            }
        }

        return $input;
    }

    public function importColumns(): array
    {
        return [
            'nombre' => 'first_name', 'apellido' => 'last_name', 'dni' => 'dni', 'cuil' => 'cuil', 'fecha_nacimiento' => 'birth_date',
            'telefono' => 'phone', 'email' => 'email', 'direccion' => 'address', 'localidad' => 'locality',
            'licencia_numero' => 'license_number', 'licencia_categoria' => 'license_category', 'licencia_vencimiento' => 'license_expires_on',
            'transportista' => 'transporter', 'contacto_emergencia' => 'emergency_contact', 'telefono_emergencia' => 'emergency_phone',
            'observaciones' => 'notes',
        ];
    }

    public function importKeys(): array
    {
        return ['dni'];
    }

    public function importRelations(): array
    {
        return ['transporter' => 'transporter_id'];
    }

    public function prepareImport(array $row): array
    {
        if (array_key_exists('transporter', $row)) {
            $row['transporter_id'] = TruckDefinition::transporterIdFrom($row['transporter']);
            unset($row['transporter']);
        }

        return $row;
    }

    public function exportValue(Model $record, string $field): mixed
    {
        return $field === 'transporter' ? $record->transporter?->business_name : parent::exportValue($record, $field);
    }

    /** Estado visual de una fecha de vencimiento: [texto, color] o null si no hay fecha. */
    public static function licenseStatus(?\DateTimeInterface $expires): ?array
    {
        if ($expires === null) {
            return null;
        }
        $days = (int) today()->diffInDays(\Illuminate\Support\Carbon::instance($expires)->startOfDay(), false);

        return match (true) {
            $days < 0 => ['Vencida', 'red'],
            $days === 0 => ['Vence hoy', 'red'],
            $days <= self::LICENSE_WARNING_DAYS => ['Vence en '.$days.' '.($days === 1 ? 'día' : 'días'), 'amber'],
            default => null,
        };
    }
}
