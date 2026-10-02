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
            Field::number('year', 'Año')->attrs(['min' => 1950, 'max' => 2100]),
            Field::text('chassis_number', 'N° de chasis'),
            Field::number('capacity_kg', 'Capacidad (kg)', '0.01')->display(fn (Model $r) => $r->capacity_kg ? kg($r->capacity_kg) : null),
            Field::number('capacity_pallets', 'Capacidad (pallets)'),
            Field::text('insurance_company', 'Aseguradora'),
            Field::text('insurance_policy', 'N° de póliza'),
            Field::date('insurance_expires_on', 'Vencimiento del seguro'),
            Field::date('vtv_expires_on', 'Vencimiento VTV / RTO'),
            Field::date('senasa_expires_on', 'Vencimiento habilitación SENASA'),
            Field::textarea('notes', 'Observaciones'),
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
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'chassis_number' => ['nullable', 'string', 'max:40'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'capacity_pallets' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'insurance_company' => ['nullable', 'string', 'max:120'],
            'insurance_policy' => ['nullable', 'string', 'max:60'],
            'insurance_expires_on' => ['nullable', 'date'],
            'vtv_expires_on' => ['nullable', 'date'],
            'senasa_expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function importColumns(): array
    {
        return [
            'patente' => 'plate', 'transportista' => 'transporter', 'tipo' => 'type', 'marca' => 'brand', 'modelo' => 'model', 'anio' => 'year',
            'chasis' => 'chassis_number', 'capacidad_kg' => 'capacity_kg', 'capacidad_pallets' => 'capacity_pallets',
            'aseguradora' => 'insurance_company', 'poliza' => 'insurance_policy', 'seguro_vencimiento' => 'insurance_expires_on',
            'vtv_vencimiento' => 'vtv_expires_on', 'senasa_vencimiento' => 'senasa_expires_on', 'observaciones' => 'notes',
        ];
    }

    public function importKeys(): array
    {
        return ['plate'];
    }

    public function importRelations(): array
    {
        return ['transporter' => 'transporter_id'];
    }

    public function prepareImport(array $row): array
    {
        if (array_key_exists('transporter', $row)) {
            $row['transporter_id'] = self::transporterIdFrom($row['transporter']);
            unset($row['transporter']);
        }
        // El tipo se acepta por clave («semi») o por nombre («Semirremolque»).
        if (isset($row['type']) && ! array_key_exists($row['type'], self::TYPES)) {
            $row['type'] = array_search(mb_strtolower(trim((string) $row['type'])), array_map('mb_strtolower', self::TYPES), true) ?: $row['type'];
        }
        $row['capacity_kg'] = isset($row['capacity_kg']) ? parse_number($row['capacity_kg']) : null;

        return $row;
    }

    public function exportValue(Model $record, string $field): mixed
    {
        return match ($field) {
            'transporter' => $record->transporter?->business_name,
            'type' => self::TYPES[$record->type] ?? $record->type,
            default => parent::exportValue($record, $field),
        };
    }

    /** Transportista por CUIT (con o sin guiones) o por razón social exacta; null si no se encuentra. */
    public static function transporterIdFrom(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $text);
        $query = Transporter::query();
        $id = strlen($digits) === 11 ? (clone $query)->where('cuit', $digits)->value('id') : null;

        return $id ?? (clone $query)->whereRaw('LOWER(business_name) = ?', [mb_strtolower($text)])->value('id') ?? -1;
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
