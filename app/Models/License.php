<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Licencia de una instalación. Nunca se usa para bloquear datos: una licencia
 * vencida sólo muestra un aviso controlado.
 */
class License extends Model
{
    use Auditable;

    protected $fillable = [
        'installation_id', 'client_name', 'license_key', 'plan', 'starts_on', 'expires_on', 'status',
        'modules', 'version', 'last_seen_at', 'notes', 'contact_name', 'contact_phone', 'contact_email', 'locality',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'expires_on' => 'date',
            'modules' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function usageReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UsageReport::class);
    }

    public function latestReport(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UsageReport::class)->latestOfMany('reported_at');
    }

    public function tickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientTicket::class);
    }

    /** En el Panel General: conectado si reportó en las últimas 26 horas. */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subHours(26));
    }

    /** Estado efectivo: una licencia activa con fecha pasada se considera vencida. */
    public function effectiveStatus(): string
    {
        if ($this->status === 'active' && $this->expires_on !== null && $this->expires_on->endOfDay()->isPast()) {
            return 'expired';
        }

        return $this->status;
    }
}
