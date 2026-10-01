<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Transporter;
use App\Rules\Cuit;
use Illuminate\Database\Eloquent\Model;

class TransporterDefinition extends CatalogDefinition
{
    protected string $model = Transporter::class;

    protected string $key = 'transporters';

    protected string $uri = 'catalogos/transportistas';

    protected string $title = 'Transportistas';

    protected string $singular = 'transportista';

    protected string $icon = 'truck';

    protected string $group = 'Logística';

    protected string $description = 'Empresas de transporte con sus camiones y choferes.';

    protected array $searchable = ['business_name', 'cuit', 'contact'];

    protected array $orderBy = ['business_name' => 'asc'];

    protected array $inUseRelations = ['loads'];

    public function recordLabel(Model $record): string
    {
        return (string) $record->business_name;
    }

    public function fields(): array
    {
        return [
            Field::text('business_name', 'Razón social')->required(),
            Field::text('cuit', 'CUIT')->attrs(['inputmode' => 'numeric'])->display(fn (Model $r) => Cuit::format($r->cuit)),
            Field::text('contact', 'Contacto'),
            Field::text('phone', 'Teléfono'),
            Field::email('email', 'Email'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Razón social', 'business_name')->strong(),
            Column::make('CUIT', fn (Transporter $t) => Cuit::format($t->cuit)),
            Column::make('Contacto', 'contact'),
            Column::make('Teléfono', 'phone'),
            Column::make('Camiones', 'trucks_count')->num(),
            Column::make('Choferes', 'drivers_count')->num(),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount(['trucks', 'drivers']);
    }

    public function rules(?Model $record): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'cuit' => ['nullable', new Cuit],
            'contact' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        return $this->cleanCuit($input);
    }

    public function showData(Model $record): array
    {
        $record->load(['trucks' => fn ($q) => $q->orderBy('plate'), 'drivers' => fn ($q) => $q->orderBy('last_name')]);

        return [
            'related' => [
                [
                    'title' => 'Camiones',
                    'items' => $record->trucks->map(fn ($t) => [
                        'label' => $t->plate, 'meta' => trim($t->brand.' '.$t->model), 'url' => route('catalogs.trucks.show', $t), 'active' => $t->active,
                    ]),
                ],
                [
                    'title' => 'Choferes',
                    'items' => $record->drivers->map(fn ($d) => [
                        'label' => $d->full_name, 'meta' => 'DNI '.$d->dni, 'url' => route('catalogs.drivers.show', $d), 'active' => $d->active,
                    ]),
                ],
            ],
        ];
    }
}
