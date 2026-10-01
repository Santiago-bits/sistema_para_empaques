<?php

namespace App\Models;

use App\Enums\RemitoStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remito extends Model
{
    use Auditable, HasStateHistory, SoftDeletes;

    protected $fillable = [
        'number', 'load_id', 'active_load_id', 'client_id', 'destination_id', 'truck_id', 'driver_id', 'issued_at',
        'total_crates', 'total_kg', 'status', 'public_token', 'notes', 'delivered_at', 'receiver_name',
        'receiver_dni', 'signature_path', 'delivery_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'delivered_at' => 'datetime',
            'total_kg' => 'decimal:2',
            'status' => RemitoStatus::class,
        ];
    }

    public function loadRecord(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RemitoItem::class);
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
