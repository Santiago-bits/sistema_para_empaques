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
        'monthly_fee', 'fee_currency', 'paid_until',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'expires_on' => 'date',
            'modules' => 'array',
            'last_seen_at' => 'datetime',
            'monthly_fee' => 'decimal:2',
            'paid_until' => 'date',
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

    /** Estados de pago (administración general). */
    public const PAYMENT_STATUSES = [
        'ok' => ['Al día', 'green'],
        'due_soon' => ['Por vencer', 'amber'],
        'overdue' => ['Vencido', 'red'],
        'never' => ['Sin pagos', 'red'],
        'free' => ['Sin cuota', 'stone'],
    ];

    /** Días antes del vencimiento en que el pago figura «por vencer». */
    public const DUE_SOON_DAYS = 7;

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LicensePayment::class);
    }

    /** ok | due_soon | overdue | never | free (sin cuota cargada). */
    public function paymentStatus(): string
    {
        if ($this->monthly_fee === null || (float) $this->monthly_fee <= 0) {
            return 'free';
        }
        if ($this->paid_until === null) {
            return 'never';
        }
        if ($this->paid_until->lt(today())) {
            return 'overdue';
        }

        return $this->paid_until->lte(today()->addDays(self::DUE_SOON_DAYS)) ? 'due_soon' : 'ok';
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->paymentStatus()][0];
    }

    public function paymentColor(): string
    {
        return self::PAYMENT_STATUSES[$this->paymentStatus()][1];
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
