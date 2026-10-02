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
            Field::text('renspa', 'RENSPA')->placeholder('00.000.0.00000/00')->hint('Registro Nacional Sanitario de Productores Agropecuarios.'),
            Field::select('tax_condition', 'Condición frente al IVA', \App\Models\Client::TAX_CONDITIONS)->placeholder('—'),
            Field::text('cbu', 'CBU')->attrs(['inputmode' => 'numeric'])->hint('22 dígitos: para pagarle la fruta por transferencia.'),
            Field::text('bank_alias', 'Alias bancario'),
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
            'renspa' => ['nullable', 'string', 'max:30'],
            'tax_condition' => ['nullable', 'in:'.implode(',', array_keys(\App\Models\Client::TAX_CONDITIONS))],
            'cbu' => ['nullable', 'digits:22'],
            'bank_alias' => ['nullable', 'string', 'max:60'],
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
        return $this->cleanBank($this->cleanCuit($this->cleanUpper($input, 'code')));
    }

    public function importColumns(): array
    {
        return [
            'codigo' => 'code', 'nombre' => 'name', 'cuit' => 'cuit', 'condicion_iva' => 'tax_condition', 'renspa' => 'renspa',
            'telefono' => 'phone', 'email' => 'email', 'direccion' => 'address', 'localidad' => 'locality', 'provincia' => 'province',
            'cbu' => 'cbu', 'alias' => 'bank_alias', 'observaciones' => 'notes',
        ];
    }
}
