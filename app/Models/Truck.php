<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Truck extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'plate', 'brand', 'model', 'transporter_id', 'capacity_kg', 'capacity_pallets', 'type', 'active', 'year', 'chassis_number',
        'insurance_company', 'insurance_policy', 'insurance_expires_on', 'vtv_expires_on', 'senasa_expires_on', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'capacity_kg' => 'decimal:2',
            'insurance_expires_on' => 'date',
            'vtv_expires_on' => 'date',
            'senasa_expires_on' => 'date',
        ];
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function loads(): HasMany
    {
        return $this->hasMany(Load::class);
    }
}
