<?php

namespace App\Catalogs\Definitions;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\Column;
use App\Catalogs\Field;
use App\Catalogs\Filter;
use App\Models\Client;
use App\Rules\Cuit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientDefinition extends CatalogDefinition
{
    protected string $model = Client::class;

    protected string $key = 'clients';

    protected string $uri = 'catalogos/clientes';

    protected string $title = 'Clientes';

    protected string $singular = 'cliente';

    protected string $icon = 'users';

    protected string $description = 'Quién recibe y paga la mercadería (facturación).';

    protected array $searchable = ['business_name', 'name', 'cuit', 'locality'];

    protected array $orderBy = ['business_name' => 'asc'];

    protected array $inUseRelations = ['loads', 'invoices'];

    /** Variantes aceptadas en formularios e importaciones para la condición frente al IVA. */
    private const TAX_ALIASES = [
        'RESPONSABLE INSCRIPTO' => 'RI', 'RESP. INSCRIPTO' => 'RI', 'INSCRIPTO' => 'RI',
        'MONOTRIBUTO' => 'MT', 'MONOTRIBUTISTA' => 'MT', 'RESPONSABLE MONOTRIBUTO' => 'MT',
        'CONSUMIDOR FINAL' => 'CF', 'EXENTO' => 'EX', 'IVA EXENTO' => 'EX',
    ];

    public function recordLabel(Model $record): string
    {
        return (string) $record->business_name;
    }

    public function fields(): array
    {
        return [
            Field::text('business_name', 'Razón social')->required(),
            Field::text('name', 'Nombre de fantasía'),
            Field::select('tax_condition', 'Condición frente al IVA', Client::TAX_CONDITIONS)->required(),
            Field::text('cuit', 'CUIT')->hint('Obligatorio salvo Consumidor Final.')->attrs(['inputmode' => 'numeric'])
                ->display(fn (Model $r) => Cuit::format($r->cuit)),
            Field::text('dni', 'DNI')->attrs(['inputmode' => 'numeric']),
            Field::text('address', 'Dirección'),
            Field::text('locality', 'Localidad'),
            Field::text('province', 'Provincia'),
            Field::text('phone', 'Teléfono'),
            Field::email('email', 'Email'),
            Field::textarea('notes', 'Observaciones'),
            Field::checkbox('active', 'Activo'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Razón social', 'business_name')->strong(),
            Column::make('Fantasía', 'name'),
            Column::make('CUIT', fn (Client $c) => Cuit::format($c->cuit))->code(),
            Column::make('Condición IVA', fn (Client $c) => [Client::TAX_CONDITIONS[$c->tax_condition] ?? $c->tax_condition, 'sky'])->as('badge'),
            Column::make('Localidad', fn (Client $c) => collect([$c->locality, $c->province])->filter()->join(', ')),
            Column::make('Estado', 'active')->as('active'),
        ];
    }

    public function filters(): array
    {
        return [...parent::filters(), Filter::equals('tax_condition', 'Condición IVA', Client::TAX_CONDITIONS)];
    }

    public function rules(?Model $record): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'tax_condition' => ['required', Rule::in(array_keys(Client::TAX_CONDITIONS))],
            'cuit' => ['nullable', 'required_unless:tax_condition,CF', new Cuit, $this->unique('cuit', $record, true)],
            'dni' => ['nullable', 'digits_between:7,9'],
            'address' => ['nullable', 'string', 'max:255'],
            'locality' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['tax_condition' => 'condición frente al IVA'];
    }

    public function defaults(): array
    {
        return parent::defaults() + ['tax_condition' => 'RI'];
    }

    public function prepare(array $input): array
    {
        $input = $this->cleanCuit($input);
        if (isset($input['dni']) && is_scalar($input['dni'])) {
            $input['dni'] = preg_replace('/\D/', '', (string) $input['dni']) ?: null;
        }
        if (isset($input['tax_condition']) && is_string($input['tax_condition'])) {
            $value = Str::upper(Str::ascii(trim($input['tax_condition'])));
            $input['tax_condition'] = self::TAX_ALIASES[$value] ?? $value;
        }

        return $input;
    }

    public function importColumns(): array
    {
        return [
            'razon_social' => 'business_name', 'nombre_fantasia' => 'name', 'cuit' => 'cuit', 'dni' => 'dni',
            'condicion_iva' => 'tax_condition', 'direccion' => 'address', 'localidad' => 'locality',
            'provincia' => 'province', 'telefono' => 'phone', 'email' => 'email', 'observaciones' => 'notes',
        ];
    }

    public function importKeys(): array
    {
        return ['cuit'];
    }
}
