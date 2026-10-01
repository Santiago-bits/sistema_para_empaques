<?php

namespace App\Models;

use App\Enums\LoadStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Load extends Model
{
    use Auditable, BelongsToWarehouse, HasFactory, HasStateHistory, SoftDeletes;

    protected $fillable = [
        'warehouse_id', 'number', 'date', 'truck_id', 'driver_id', 'transporter_id', 'destination_id',
        'client_id', 'owner_id', 'status', 'planned_crates', 'total_crates', 'total_kg', 'notes',
        'closed_at', 'closed_by', 'dispatched_at', 'dispatched_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => LoadStatus::class,
            'total_kg' => 'decimal:2',
            'closed_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class, 'current_load_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LoadCrate::class);
    }

    public function dispatchChecks(): HasMany
    {
        return $this->hasMany(DispatchCheck::class);
    }

    public function remito(): HasOne
    {
        return $this->hasOne(Remito::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
