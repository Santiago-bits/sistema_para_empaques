<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CodeSuggester;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Models\Producer;
use App\Rules\Cuit;
use Illuminate\Database\Eloquent\Model;

class ProducerDefinition extends CatalogDefinition
{
    protected string $model = Producer::class;

    protected string $key = 'producers';

    protected string $uri = 'catalogos/productores';

    protected string $title = 'Productores';

    protected string $singular = 'productor';

    protected string $icon = 'user-chart';

    protected string $description = 'Quién produjo la fruta (origen de lotes y pallets).';

    protected array $searchable = ['code', 'name', 'cuit', 'locality'];

    protected array $inUseRelations = ['lots', 'pallets', 'crates'];

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
            Field::text('locality', 'Localidad'),
            Field::text('province', 'Provincia'),
            Field::textarea('notes', 'Observaciones'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'code')->code(),
            Column::make('Nombre', 'name')->strong(),
            Column::make('CUIT', fn (Producer $p) => Cuit::format($p->cuit)),
            Column::make('Localidad', fn (Producer $p) => collect([$p->locality, $p->province])->filter()->join(', ')),
            Column::make('Teléfono', 'phone'),
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
            'locality' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['code' => CodeSuggester::next(Producer::class, 'PRD')];
    }

    public function prepare(array $input): array
    {
        return $this->cleanCuit($this->cleanUpper($input, 'code'));
    }

    public function importColumns(): array
    {
        return [
            'codigo' => 'code', 'nombre' => 'name', 'cuit' => 'cuit', 'telefono' => 'phone', 'email' => 'email',
            'direccion' => 'address', 'localidad' => 'locality', 'provincia' => 'province', 'observaciones' => 'notes',
        ];
    }
}
