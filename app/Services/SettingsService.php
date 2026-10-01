<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración general del sistema (tabla settings) con cache.
 * Uso: setting('production.weight_min', 5) o app(SettingsService::class)->set(...).
 */
class SettingsService
{
    private const CACHE_KEY = 'galpon.settings';

    /** Valores por defecto: si una clave no existe en la base se usa este valor. */
    public const DEFAULTS = [
        'company.name' => ['Galpón de Empaque', 'string', 'company'],
        'company.cuit' => ['', 'string', 'company'],
        'company.address' => ['', 'string', 'company'],
        'company.phone' => ['', 'string', 'company'],
        'company.email' => ['', 'string', 'company'],
        'company.logo' => ['', 'string', 'company'],
        'ui.primary_color' => ['#16a34a', 'string', 'ui'],
        'regional.currency' => ['ARS', 'string', 'regional'],
        'regional.timezone' => ['America/Argentina/Buenos_Aires', 'string', 'regional'],
        'regional.date_format' => ['d/m/Y', 'string', 'regional'],
        'login.identifiers' => [['username', 'dni', 'cuit', 'internal_code', 'email'], 'json', 'security'],
        'security.session_lifetime' => [480, 'int', 'security'],
        'network.lan_only_modules' => [[], 'json', 'security'],
        'network.allowed_ranges' => [['127.0.0.1/32', '192.168.0.0/16', '10.0.0.0/8', '172.16.0.0/12'], 'json', 'security'],
        'production.weight_min' => [5, 'float', 'production'],
        'production.weight_max' => [30, 'float', 'production'],
        'production.require_lot' => [false, 'bool', 'production'],
        'production.sound_success' => [true, 'bool', 'production'],
        'production.sound_error' => [true, 'bool', 'production'],
        'production.sound_duplicate' => [true, 'bool', 'production'],
        'production.auto_create_crate' => [true, 'bool', 'production'],
        'production.scale_driver' => ['manual', 'string', 'production'],
        'production.target_daily_kg' => [10000, 'float', 'production'],
        'production.target_weekly_kg' => [60000, 'float', 'production'],
        'production.target_monthly_kg' => [240000, 'float', 'production'],
        'fields.crate' => [[
            'weight' => 'required', 'lot_id' => 'optional', 'notes' => 'optional', 'pallet_id' => 'optional',
        ], 'json', 'fields'],
        'alerts.enabled' => [[
            'stock_low' => true, 'load_pending' => true, 'temperature' => true, 'production_low' => false,
            'document_expiring' => true, 'maintenance_due' => true, 'crates_unprocessed' => true,
        ], 'json', 'alerts'],
        'alerts.load_pending_hours' => [24, 'int', 'alerts'],
        'alerts.document_expiring_days' => [15, 'int', 'alerts'],
        'alerts.crates_unprocessed_hours' => [12, 'int', 'alerts'],
        'arca.mode' => ['simulation', 'string', 'arca'],
        'arca.point_of_sale' => [1, 'int', 'arca'],
        'arca.cuit' => ['', 'string', 'arca'],
        'arca.emitter_condition' => ['RI', 'string', 'arca'], // RI = Responsable Inscripto (A/B), MT = Monotributo (C)
        'backup.retention_days' => [30, 'int', 'backup'],
        'backup.daily' => [true, 'bool', 'backup'],
        'backup.weekly' => [true, 'bool', 'backup'],
        'whatsapp.enabled' => [false, 'bool', 'integrations'],
        'system.installed' => [false, 'bool', 'system'],
        'system.environment_label' => ['', 'string', 'system'],
    ];

    private ?array $values = null;

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $stored = [];
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->get(['key', 'value', 'type'])
                    ->mapWithKeys(fn (Setting $s) => [$s->key => $this->cast($s->value, $s->type)])
                    ->all();
            });
        } catch (\Throwable) {
            // Sin base de datos (instalación inicial): se usan los valores por defecto.
        }

        $defaults = array_map(fn ($d) => $d[0], self::DEFAULTS);

        return $this->values = array_replace($defaults, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value, ?string $type = null): void
    {
        $type ??= self::DEFAULTS[$key][1] ?? (is_array($value) ? 'json' : 'string');
        $group = self::DEFAULTS[$key][2] ?? explode('.', $key)[0];

        $setting = Setting::query()->firstOrNew(['key' => $key]);
        $setting->fill(['value' => $this->serialize($value, $type), 'type' => $type, 'group' => $group])->save();

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    private function serialize(mixed $value, string $type): ?string
    {
        return match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            default => $value === null ? null : (string) $value,
        };
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'json' => $value === null ? [] : json_decode($value, true),
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }
}
