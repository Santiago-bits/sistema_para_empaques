<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CodeSuggester;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Owner;
use App\Rules\Cuit;
use Illuminate\Database\Eloquent\Model;

class OwnerDefinition extends CatalogDefinition
{
    protected string $model = Owner::class;

    protected string $key = 'owners';

    protected string $uri = 'catalogos/propietarios';

    protected string $title = 'Propietarios';

    protected string $singular = 'propietario';

    protected string $icon = 'briefcase';

    protected string $description = 'Dueños de la mercadería (pueden diferir del productor).';

    protected array $searchable = ['code', 'name', 'cuit'];

    protected array $inUseRelations = ['lots', 'pallets', 'crates', 'loads'];

    public function fields(): array
    {
        return [
            Field::text('code', 'Código')->required()->attrs(['maxlength' => 30]),
            Field::text('name', 'Nombre o razón social')->required(),
            Field::text('cuit', 'CUIT')->hint('11 dígitos, con o sin guiones.')->attrs(['inputmode' => 'numeric'])
                ->display(fn (Model $r) => Cuit::format($r->cuit)),
            Field::text('phone', 'Teléfono'),
            Field::email('email', 'Email'),
            Field::text('address', 'Dirección'),
            Field::textarea('notes', 'Observaciones'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('CUIT', fn (Owner $o) => Cuit::format($o->cuit)),
            Column::make('Teléfono', 'phone'),
            Column::make('Email', 'email'),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', $this->unique('code', $record)],
            'name' => ['required', 'string', 'max:255'],
            'cuit' => ['nullable', new Cuit],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['code' => CodeSuggester::next(Owner::class, 'PROP')];
    }

    public function prepare(array $input): array
    {
        return $this->cleanCuit($this->cleanUpper($input, 'code'));
    }

    public function importColumns(): array
    {
        return [
            'codigo' => 'code', 'nombre' => 'name', 'cuit' => 'cuit', 'telefono' => 'phone', 'email' => 'email',
            'direccion' => 'address', 'observaciones' => 'notes',
        ];
    }
}
