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
            Field::text('phone', 'Teléfono'),
            Field::text('license_number', 'N° de licencia'),
            Field::date('license_expires_on', 'Vencimiento de licencia'),
            Field::select('transporter_id', 'Transportista', fn () => TruckDefinition::transporterOptions())->placeholder('Sin transportista')
                ->display(fn (Model $r) => $r->transporter?->business_name),
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
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:40'],
            'license_expires_on' => ['nullable', 'date'],
            'transporter_id' => ['nullable', 'integer', 'exists:transporters,id'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        if (isset($input['dni']) && is_scalar($input['dni'])) {
            $input['dni'] = preg_replace('/\D/', '', (string) $input['dni']);
        }

        return $input;
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
