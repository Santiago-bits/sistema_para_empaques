<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use Auditable, BelongsToWarehouse;

    protected $fillable = [
        'warehouse_id', 'date', 'snapshot', 'closed_by', 'closed_at', 'reopened_at', 'reopened_by',
        'reopen_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'snapshot' => 'array',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isOpen(): bool
    {
        return $this->reopened_at !== null;
    }
}
