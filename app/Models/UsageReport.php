<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reporte de uso que envía un empaque al Panel General (sólo totales, sin datos personales). */
class UsageReport extends Model
{
    protected $fillable = ['license_id', 'reported_at', 'version', 'metrics', 'ip'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime', 'metrics' => 'array'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function metric(string $key, mixed $default = 0): mixed
    {
        return $this->metrics[$key] ?? $default;
    }
}
