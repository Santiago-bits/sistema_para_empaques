<?php

namespace App\Catalogs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Definición declarativa de un catálogo: modelo, rutas, permisos, campos del
 * formulario, columnas del listado, filtros, reglas de validación y columnas de
 * importación. CatalogController, CatalogRequest, las vistas genéricas y el
 * ImportService trabajan sólo con esta definición (sin código duplicado por catálogo).
 */
abstract class CatalogDefinition
{
    /** @var class-string<Model> */
    protected string $model;

    /** Clave en inglés plural (nombre de ruta: catalogs.<key>.index). */
    protected string $key;

    /** URI en español (p.ej. catalogos/productores). */
    protected string $uri;

    /** Prefijo del nombre de ruta. */
    protected string $routePrefix = 'catalogs.';

    protected string $title;

    protected string $singular;

    protected bool $feminine = false;

    protected string $icon = 'book';

    protected string $description = '';

    /** Grupo de la página de catálogos. */
    protected string $group = 'Comercial';

    protected string $viewPermission = 'catalogs.view';

    protected string $managePermission = 'catalogs.manage';

    /** @var list<string> Columnas para la búsqueda libre (?q=). */
    protected array $searchable = ['name'];

    /** @var array<string, string> */
    protected array $orderBy = ['name' => 'asc'];

    /** @var list<string> Relaciones a precargar en el listado. */
    protected array $with = [];

    /** @var list<string> Relaciones que impiden eliminar el registro (si tiene datos asociados). */
    protected array $inUseRelations = [];

    /** @return list<Field> */
    abstract public function fields(): array;

    /** @return list<Column> */
    abstract public function columns(): array;

    /** @return array<string, mixed> */
    abstract public function rules(?Model $record): array;

    // ---------------------------------------------------------------- Identidad

    public function key(): string
    {
        return $this->key;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function routeName(): string
    {
        return $this->routePrefix.$this->key;
    }

    public function route(string $action, mixed $parameters = []): string
    {
        return route($this->routeName().'.'.$action, $parameters);
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return $this->model;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function singular(): string
    {
        return $this->singular;
    }

    /** "Productor creado." / "Temporada creada." */
    public function savedMessage(bool $created): string
    {
        $verb = $created ? 'cread' : 'actualizad';

        return Str::ucfirst($this->singular).' '.$verb.($this->feminine ? 'a.' : 'o.');
    }

    public function newLabel(): string
    {
        return ($this->feminine ? 'Nueva ' : 'Nuevo ').$this->singular;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function viewPermission(): string
    {
        return $this->viewPermission;
    }

    public function managePermission(): string
    {
        return $this->managePermission;
    }

    public function recordLabel(Model $record): string
    {
        return (string) ($record->getAttribute('name') ?? $record->getAttribute('business_name') ?? '#'.$record->getKey());
    }

    // ---------------------------------------------------------------- Capacidades

    public function hasActive(): bool
    {
        return in_array('active', (new $this->model)->getFillable(), true);
    }

    public function hasToggle(): bool
    {
        return $this->hasActive();
    }

    public function toggleLabel(Model $record): string
    {
        return $record->getAttribute('active') ? 'Desactivar' : 'Activar';
    }

    /** Cambia el estado activo/inactivo (se ejecuta dentro de una transacción). */
    public function toggle(Model $record): string
    {
        $record->update(['active' => ! $record->getAttribute('active')]);

        $suffix = $this->feminine ? 'a.' : 'o.';

        return Str::ucfirst($this->singular).($record->getAttribute('active') ? ' activad' : ' desactivad').$suffix;
    }

    /** Sólo se ofrece eliminar (lógicamente) en modelos con SoftDeletes. */
    public function canDelete(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    public function inUse(Model $record): bool
    {
        foreach ($this->inUseRelations as $relation) {
            if ($record->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
    }

    public function hasShow(): bool
    {
        return true;
    }

    public function showView(): string
    {
        return 'catalogs.show';
    }

    /** Datos adicionales para la ficha. */
    public function showData(Model $record): array
    {
        return [];
    }

    /**
     * Acciones extra en el encabezado del listado.
     *
     * @return list<array{label: string, url: string, icon: string, permission: string}>
     */
    public function headerActions(): array
    {
        return [];
    }

    /**
     * Acción masiva sobre registros seleccionados (checkboxes ids[] enviados por GET).
     *
     * @return array{label: string, route: string, icon: string}|null
     */
    public function bulkAction(): ?array
    {
        return null;
    }

    // ---------------------------------------------------------------- Formulario

    /** @return array<string, mixed> Valores iniciales de un registro nuevo. */
    public function defaults(): array
    {
        return $this->hasActive() ? ['active' => true] : [];
    }

    public function newRecord(): Model
    {
        return new $this->model($this->defaults());
    }

    /** Normaliza la entrada antes de validar (mayúsculas, CUIT sin guiones, etc.). */
    public function prepare(array $input): array
    {
        return $input;
    }

    /** Se ejecuta dentro de la misma transacción luego de guardar. */
    public function afterSave(Model $record, bool $created): void
    {
    }

    /** @return array<string, string> nombres legibles para los mensajes de validación. */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->fields() as $field) {
            // Las siglas (CUIT, DNI) se mantienen en mayúsculas: "El CUIT no es válido".
            $attributes[$field->name] = mb_strtoupper($field->label) === $field->label ? $field->label : Str::lower($field->label);
        }

        return $attributes;
    }

    /** Regla unique que ignora el registro actual. */
    protected function unique(string $column, ?Model $record, bool $ignoreTrashed = false): Unique
    {
        $rule = Rule::unique((new $this->model)->getTable(), $column)->ignore($record?->getKey());

        return $ignoreTrashed ? $rule->withoutTrashed() : $rule;
    }

    protected function cleanUpper(array $input, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = mb_strtoupper(trim($input[$key]));
            }
        }

        return $input;
    }

    protected function cleanCuit(array $input, string $key = 'cuit'): array
    {
        if (array_key_exists($key, $input)) {
            $input[$key] = \App\Rules\Cuit::normalize(is_scalar($input[$key]) ? (string) $input[$key] : null);
        }

        return $input;
    }

    // ---------------------------------------------------------------- Consultas

    public function query(): Builder
    {
        return $this->model::query()->with($this->with);
    }

    /** @return list<Filter> */
    public function filters(): array
    {
        return $this->hasActive() ? [Filter::active()] : [];
    }

    public function applySearch(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(function (Builder $w) use ($like) {
            foreach ($this->searchable as $column) {
                $w->orWhere($column, 'like', $like);
            }
        });
    }

    public function applyOrder(Builder $query): void
    {
        foreach ($this->orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }
    }

    public function find(mixed $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function count(): int
    {
        $query = $this->model::query();

        return $this->hasActive() ? $query->where('active', true)->count() : $query->count();
    }

    // ---------------------------------------------------------------- Importación

    /**
     * Columnas de la planilla de importación: encabezado => campo del modelo.
     * Vacío = el catálogo no se puede importar.
     *
     * @return array<string, string>
     */
    public function importColumns(): array
    {
        return [];
    }

    /** @return list<string> Campos que no pueden repetirse dentro del archivo. */
    public function importKeys(): array
    {
        return ['code'];
    }

    /** Ajusta una fila importada (ya mapeada a campos) antes de prepare() y validar. */
    public function prepareImport(array $row): array
    {
        return $row;
    }
}
