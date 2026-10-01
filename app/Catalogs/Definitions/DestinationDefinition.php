<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Client;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Model;

class DestinationDefinition extends CatalogDefinition
{
    protected string $model = Destination::class;

    protected string $key = 'destinations';

    protected string $uri = 'catalogos/destinos';

    protected string $title = 'Destinos';

    protected string $singular = 'destino';

    protected string $icon = 'pin';

    protected string $description = 'Lugares de entrega (mercados, depósitos, puertos).';

    protected array $searchable = ['name', 'locality', 'province', 'address'];

    protected array $with = ['client'];

    protected array $inUseRelations = ['loads'];

    public function fields(): array
    {
        return [
            Field::text('name', 'Nombre')->required(),
            Field::select('client_id', 'Cliente', fn () => self::clientOptions())->placeholder('Sin cliente asociado')
                ->display(fn (Model $r) => $r->client?->business_name),
            Field::text('address', 'Dirección'),
            Field::text('locality', 'Localidad'),
            Field::text('province', 'Provincia'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Nombre', 'name')->strong(),
            Column::make('Cliente', 'client.business_name'),
            Column::make('Dirección', 'address'),
            Column::make('Localidad', fn (Destination $d) => collect([$d->locality, $d->province])->filter()->join(', ')),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('client_id', 'Cliente', fn () => self::clientOptions())];
    }

    public function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'locality' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'active' => ['boolean'],
        ];
    }

    public static function clientOptions(): array
    {
        return Client::query()->where('active', true)->orderBy('business_name')->pluck('business_name', 'id')->all();
    }
}
