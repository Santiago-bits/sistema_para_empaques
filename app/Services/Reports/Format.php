<?php

namespace App\Services\Reports;

use DateTimeInterface;

/** Formato de celdas de reportes para pantalla/PDF (es-AR) y para planillas (valores crudos). */
final class Format
{
    public const NUMERIC = ['int', 'kg', 'decimal', 'pct', 'money', 'minutes'];

    public static function display(mixed $value, string $type = 'text'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'int' => num($value),
            'kg' => kg($value),
            'decimal' => num($value, 2),
            'pct' => pct($value),
            'money' => money($value),
            'minutes' => self::minutes((float) $value),
            'date' => $value instanceof DateTimeInterface ? $value->format('d/m/Y') : date('d/m/Y', strtotime((string) $value)),
            'datetime' => $value instanceof DateTimeInterface ? $value->format('d/m/Y H:i') : date('d/m/Y H:i', strtotime((string) $value)),
            default => (string) $value,
        };
    }

    /** Valor para Excel/CSV: números como números, fechas como texto dd/mm/aaaa. */
    public static function cell(mixed $value, string $type = 'text'): mixed
    {
        if ($value === null) {
            return null;
        }
        if (in_array($type, self::NUMERIC, true)) {
            return is_numeric($value) ? $value + 0 : $value;
        }
        if ($type === 'date' || $type === 'datetime') {
            return self::display($value, $type);
        }

        return (string) $value;
    }

    public static function minutes(float $minutes): string
    {
        if ($minutes < 60) {
            return num($minutes, 0).' min';
        }

        return sprintf('%d h %02d min', intdiv((int) round($minutes), 60), (int) round($minutes) % 60);
    }
}
