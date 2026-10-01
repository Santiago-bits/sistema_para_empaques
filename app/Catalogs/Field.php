<?php

namespace App\Catalogs;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Campo de formulario de un catálogo. Se renderiza con los componentes Blade
 * existentes (x-input, x-select, x-textarea, x-checkbox) y sabe mostrarse en la ficha.
 */
final class Field
{
    public bool $required = false;

    public ?string $hint = null;

    public ?string $placeholder = null;

    public bool $wide = false;

    /** @var array<string, mixed> Atributos HTML extra (inputmode, maxlength, step...). */
    public array $attributes = [];

    /** @var array<string|int, string>|Closure */
    private array|Closure $options = [];

    private ?Closure $display = null;

    private function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type,
    ) {
    }

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, 'text');
    }

    public static function email(string $name, string $label): self
    {
        return new self($name, $label, 'email');
    }

    public static function number(string $name, string $label, string $step = '1'): self
    {
        return (new self($name, $label, 'number'))->attrs(['step' => $step, 'min' => '0']);
    }

    public static function date(string $name, string $label): self
    {
        return new self($name, $label, 'date');
    }

    public static function time(string $name, string $label): self
    {
        return new self($name, $label, 'time');
    }

    public static function color(string $name, string $label): self
    {
        return new self($name, $label, 'color');
    }

    public static function textarea(string $name, string $label): self
    {
        return (new self($name, $label, 'textarea'))->wide();
    }

    public static function checkbox(string $name, string $label): self
    {
        return new self($name, $label, 'checkbox');
    }

    /** @param  array<string|int, string>|Closure  $options  [valor => etiqueta] o closure perezosa. */
    public static function select(string $name, string $label, array|Closure $options): self
    {
        $field = new self($name, $label, 'select');
        $field->options = $options;

        return $field;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function hint(string $hint): self
    {
        $this->hint = $hint;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function wide(bool $wide = true): self
    {
        $this->wide = $wide;

        return $this;
    }

    public function attrs(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /** Cómo mostrar el valor en la ficha (p.ej. el nombre de la relación en lugar del id). */
    public function display(Closure $display): self
    {
        $this->display = $display;

        return $this;
    }

    /** @return array<string|int, string> */
    public function options(): array
    {
        $options = $this->options instanceof Closure ? ($this->options)() : $this->options;

        return $options instanceof \Illuminate\Support\Collection ? $options->all() : $options;
    }

    public function value(Model $record): mixed
    {
        $value = $record->getAttribute($this->name);

        return match ($this->type) {
            'time' => $value ? substr((string) $value, 0, 5) : $value,
            default => $value,
        };
    }

    public function format(Model $record): ?string
    {
        if ($this->display) {
            return ($this->display)($record);
        }

        $value = $this->value($record);

        return match ($this->type) {
            'checkbox' => $value ? 'Sí' : 'No',
            'select' => $value === null || $value === '' ? null : ($this->options()[$value] ?? (string) $value),
            'date' => $value instanceof DateTimeInterface ? fdate($value) : $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
