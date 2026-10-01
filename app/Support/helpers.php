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
