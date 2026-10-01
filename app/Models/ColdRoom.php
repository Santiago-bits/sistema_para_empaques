<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ColdRoom extends Model
{
    use Auditable;

    protected $fillable = [
        'location_id', 'code', 'name', 'temp_min', 'temp_max', 'humidity_min', 'humidity_max', 'sensor_key',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'temp_min' => 'decimal:2',
            'temp_max' => 'decimal:2',
            'humidity_min' => 'decimal:2',
            'humidity_max' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(TemperatureRecord::class);
    }

    public function isOutOfRange(float $temperature, ?float $humidity = null): bool
    {
        if ($temperature < (float) $this->temp_min || $temperature > (float) $this->temp_max) {
            return true;
        }
        if ($humidity !== null && $this->humidity_min !== null && $humidity < (float) $this->humidity_min) {
            return true;
        }

        return $humidity !== null && $this->humidity_max !== null && $humidity > (float) $this->humidity_max;
    }
}
