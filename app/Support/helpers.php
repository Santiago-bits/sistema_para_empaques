<?php

use App\Services\ModuleService;
use App\Services\SettingsService;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('module_enabled')) {
    function module_enabled(string $key): bool
    {
        return app(ModuleService::class)->enabled($key);
    }
}

if (! function_exists('kg')) {
    /** Formato argentino: 9.450,50 kg */
    function kg(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', '.').' kg';
    }
}

if (! function_exists('num')) {
    function num(float|int|string|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (! function_exists('pct')) {
    function pct(float|int|string|null $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.').' %';
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $value, ?string $currency = null): string
    {
        $currency ??= setting('regional.currency', 'ARS');
        $symbol = $currency === 'USD' ? 'US$' : '$';

        return $symbol.' '.number_format((float) $value, 2, ',', '.');
    }
}

if (! function_exists('fdate')) {
    function fdate(?\DateTimeInterface $date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }

        return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    }
}

if (! function_exists('field_label')) {
    /** Nombre legible de una columna (lang/es/fields.php); si no existe, la columna tal cual. */
    function field_label(string $column): string
    {
        $key = 'fields.'.$column;
        $label = __($key);

        return $label === $key ? $column : $label;
    }
}

if (! function_exists('parse_number')) {
    /**
     * Interpreta un número escrito por el usuario en formato argentino y lo devuelve normalizado
     * ("1234.56") para validarlo como numeric. Si no parece un número, lo devuelve tal cual
     * para que la validación lo rechace.
     *
     *  - "1.234,56" → 1234.56   ·   "1,5" → 1.5   ·   "1,234.56" → 1234.56
     *  - Con $dotThousands (importes, cantidades, stock): "1.500" → 1500 y "125.000" → 125000.
     *  - Sin $dotThousands (peso de un cajón, temperatura, porcentaje): "18.5" → 18.5.
     */
    function parse_number(mixed $value, bool $dotThousands = true): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $clean = str_replace(['$', '%', ' ', "\u{00A0}", 'kg', 'u'], '', trim($value));
        if ($clean === '') {
            return null;
        }
        if (! preg_match('/^-?[\d.,]+$/', $clean)) {
            return $value;
        }

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        if ($hasDot && $hasComma) {
            // El último separador es el decimal; el otro, de miles.
            $decimal = strrpos($clean, ',') > strrpos($clean, '.') ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $clean = str_replace([$thousands, $decimal], ['', '.'], $clean);
        } elseif ($hasComma) {
            $clean = substr_count($clean, ',') > 1 ? str_replace(',', '', $clean) : str_replace(',', '.', $clean);
        } elseif ($hasDot && (substr_count($clean, '.') > 1 || ($dotThousands && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $clean)))) {
            $clean = str_replace('.', '', $clean);
        }

        return $clean;
    }
}
