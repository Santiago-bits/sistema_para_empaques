<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use Auditable, SoftDeletes;

    public const STATUSES = ['operational' => 'Operativa', 'maintenance' => 'En mantenimiento', 'out_of_service' => 'Fuera de servicio'];

    protected $fillable = [
        'code', 'name', 'brand', 'model', 'serial_number', 'location_id', 'status', 'next_maintenance_on',
    ];

    protected function casts(): array
    {
        return [
            'next_maintenance_on' => 'date',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
