<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Provider;
use App\Rules\Cuit;
use Illuminate\Database\Eloquent\Model;

class ProviderDefinition extends CatalogDefinition
{
    protected string $model = Provider::class;

    protected string $key = 'providers';

    protected string $uri = 'catalogos/proveedores';

    protected string $title = 'Proveedores';

    protected string $singular = 'proveedor';

    protected string $icon = 'archive';

    protected string $description = 'Proveedores de insumos (cajas, etiquetas, film...).';

    protected array $searchable = ['name', 'cuit', 'contact', 'products'];

    protected array $inUseRelations = ['supplies'];

    public function fields(): array
    {
        return [
            Field::text('name', 'Nombre o razón social')->required(),
            Field::text('cuit', 'CUIT')->attrs(['inputmode' => 'numeric'])->display(fn (Model $r) => Cuit::format($r->cuit)),
            Field::text('contact', 'Contacto'),
            Field::text('phone', 'Teléfono'),
            Field::email('email', 'Email'),
            Field::text('address', 'Dirección'),
            Field::text('products', 'Productos que provee')->wide()->hint('Ej.: cajas de cartón, etiquetas, esquineros.'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Nombre', 'name')->strong(),
            Column::make('CUIT', fn (Provider $p) => Cuit::format($p->cuit)),
            Column::make('Contacto', 'contact'),
            Column::make('Teléfono', 'phone'),
            Column::make('Productos', 'products'),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cuit' => ['nullable', new Cuit],
            'contact' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'products' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ];
    }

    public function prepare(array $input): array
    {
        return $this->cleanCuit($input);
    }
}
