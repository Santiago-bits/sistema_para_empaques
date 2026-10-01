<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    protected $fillable = [
        'filename', 'disk', 'size', 'type', 'status', 'checksum', 'verified_at', 'error', 'created_by',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
