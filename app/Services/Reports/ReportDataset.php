<?php

namespace App\Services\Reports;

use Closure;

/**
 * Conjunto de datos tabular de un reporte: columnas tipadas + filas iterables.
 *
 * Las filas pueden ser un array (agregados, pocas filas) o un Closure que
 * devuelve un iterable perezoso (LazyCollection con lazyById) para detalle
 * de miles de registros: nunca se cargan todas en memoria.
 */
final class ReportDataset
{
    /**
     * @param  array<string, array{label:string, type:string}>  $columns
     * @param  iterable<array<string, mixed>>|Closure  $rows
     * @param  Closure|int|null  $count  cantidad de filas (o un Closure que la calcula con COUNT)
     * @param  array<string, mixed>  $totals  fila de totales (opcional)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $columns,
        private readonly iterable|Closure $rows,
        private readonly Closure|int|null $count = null,
        public readonly array $totals = [],
        public readonly string $variant = 'summary',
    ) {
    }

    /** @return iterable<array<string, mixed>> */
    public function rows(): iterable
    {
        return $this->rows instanceof Closure ? ($this->rows)() : $this->rows;
    }

    public function count(): int
    {
        if ($this->count instanceof Closure) {
            return (int) ($this->count)();
        }
        if ($this->count !== null) {
            return $this->count;
        }

        return is_array($this->rows) ? count($this->rows) : iterator_count($this->rows());
    }

    /** @return list<string> */
    public function headings(): array
    {
        return array_values(array_map(fn ($c) => $c['label'], $this->columns));
    }
}
