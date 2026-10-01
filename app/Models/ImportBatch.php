<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $fillable = [
        'type', 'filename', 'path', 'status', 'total_rows', 'valid_rows', 'error_rows', 'errors', 'user_id',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
