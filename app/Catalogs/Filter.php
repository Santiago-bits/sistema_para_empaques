<?php

namespace App\Catalogs;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/** Filtro GET del listado de un catálogo (se dibuja como x-select). */
final class Filter
{
    /**
     * @param  array<string|int, string>|Closure  $options
     * @param  Closure(Builder, string): void  $apply
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        private readonly array|Closure $options,
        private readonly Closure $apply,
    ) {
    }

    public static function active(): self
    {
        return new self('active', 'Estado', ['1' => 'Activos', '0' => 'Inactivos'],
            fn (Builder $q, string $value) => $q->where('active', $value === '1'));
    }

    /** Filtro por igualdad sobre una columna. */
    public static function equals(string $column, string $label, array|Closure $options): self
    {
        return new self($column, $label, $options, fn (Builder $q, string $value) => $q->where($column, $value));
    }

    public function options(): array
    {
        $options = $this->options instanceof Closure ? ($this->options)() : $this->options;

        return $options instanceof \Illuminate\Support\Collection ? $options->all() : $options;
    }

    public function apply(Builder $query, string $value): void
    {
        ($this->apply)($query, $value);
    }
}
