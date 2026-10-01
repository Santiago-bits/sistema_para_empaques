<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Transporter;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Model;

class TruckDefinition extends CatalogDefinition
{
    /** Patente argentina: Mercosur (AA123BB) o formato anterior (ABC123). */
    public const PLATE_REGEX = '/^([A-Z]{2}\d{3}[A-Z]{2}|[A-Z]{3}\d{3})$/';

    public const TYPES = [
        'chasis' => 'Chasis',
        'semi' => 'Semirremolque',
        'acoplado' => 'Chasis con acoplado',
        'refrigerado' => 'Refrigerado',
        'utilitario' => 'Utilitario',
        'otro' => 'Otro',
    ];

    protected string $model = Truck::class;

    protected string $key = 'trucks';

    protected string $uri = 'catalogos/camiones';

    protected string $title = 'Camiones';

    protected string $singular = 'camión';

    protected string $icon = 'truck';

    protected string $group = 'Logística';

    protected string $description = 'Patentes, capacidad y transportista.';

    protected array $searchable = ['plate', 'brand', 'model'];

    protected array $orderBy = ['plate' => 'asc'];

    protected array $with = ['transporter'];

    protected array $inUseRelations = ['loads'];

    public static function normalizePlate(?string $plate): ?string
    {
        if ($plate === null) {
            return null;
        }

        return mb_strtoupper(preg_replace('/[\s.\-]/', '', $plate)) ?: null;
    }

    public function recordLabel(Model $record): string
    {
        return (string) $record->plate;
    }

    public function fields(): array
    {
        return [
            Field::text('plate', 'Patente')->required()->hint('AA123BB o ABC123. Se guarda en mayúsculas y sin espacios.')
                ->attrs(['maxlength' => 12, 'class' => 'uppercase', 'autocomplete' => 'off']),
            Field::select('transporter_id', 'Transportista', fn () => self::transporterOptions())->placeholder('Sin transportista')
                ->display(fn (Model $r) => $r->transporter?->business_name),
            Field::select('type', 'Tipo', self::TYPES)->placeholder('—'),
            Field::text('brand', 'Marca'),
            Field::text('model', 'Modelo'),
            Field::number('capacity_kg', 'Capacidad (kg)', '0.01')->display(fn (Model $r) => $r->capacity_kg ? kg($r->capacity_kg) : null),
            Field::number('capacity_pallets', 'Capacidad (pallets)'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Patente', 'plate')->code(),
            Column::make('Marca / modelo', fn (Truck $t) => trim($t->brand.' '.$t->model)),
            Column::make('Tipo', fn (Truck $t) => self::TYPES[$t->type] ?? $t->type),
            Column::make('Transportista', 'transporter.business_name'),
            Column::make('Cap. kg', fn (Truck $t) => $t->capacity_kg ? num($t->capacity_kg) : null)->num(),
            Column::make('Cap. pallets', 'capacity_pallets')->num(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('transporter_id', 'Transportista', fn () => self::transporterOptions())];
    }

    public function rules(?Model $record): array
    {
        return [
            'plate' => ['required', 'string', 'max:12', 'regex:'.self::PLATE_REGEX, $this->unique('plate', $record)],
            'transporter_id' => ['nullable', 'integer', 'exists:transporters,id'],
            'type' => ['nullable', 'string', 'max:40'],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'capacity_pallets' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        if (array_key_exists('plate', $input) && (is_string($input['plate']) || $input['plate'] === null)) {
            $input['plate'] = self::normalizePlate($input['plate']);
        }

        return $input;
    }

    public static function transporterOptions(): array
    {
        return Transporter::query()->where('active', true)->orderBy('business_name')->pluck('business_name', 'id')->all();
    }
}
