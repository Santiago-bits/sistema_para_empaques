<?php

namespace App\Catalogs;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Columna del listado de un catálogo. El tipo define cómo se dibuja la celda
 * (ver resources/views/catalogs/partials/cell.blade.php).
 */
final class Column
{
    /** text | strong | code | num | active | color | date | license | badge */
    public string $type = 'text';

    private function __construct(
        public readonly string $label,
        private readonly string|Closure $value,
    ) {
    }

    /** @param  string|Closure  $value  atributo (admite notación con puntos: transporter.business_name) o closure. */
    public static function make(string $label, string|Closure $value): self
    {
        return new self($label, $value);
    }

    public function as(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function strong(): self
    {
        return $this->as('strong');
    }

    public function code(): self
    {
        return $this->as('code');
    }

    public function num(): self
    {
        return $this->as('num');
    }

    public function resolve(Model $record): mixed
    {
        return $this->value instanceof Closure ? ($this->value)($record) : data_get($record, $this->value);
    }
}
