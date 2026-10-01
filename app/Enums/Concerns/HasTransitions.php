<?php

namespace App\Enums\Concerns;

/**
 * Comportamiento compartido por los enums de estado: etiquetas, opciones para
 * selects y validación de transiciones.
 */
trait HasTransitions
{
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, static::transitions()[$this->value] ?? [], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return static::transitions()[$this->value] ?? [];
    }

    /** @return array<string, string> valor => etiqueta */
    public static function options(): array
    {
        $options = [];
        foreach (static::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, static::cases());
    }
}
