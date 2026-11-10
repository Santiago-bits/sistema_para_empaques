<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    use BelongsToWarehouse;

    protected $fillable = [
        'type', 'mode', 'filename', 'path', 'status', 'total_rows', 'valid_rows', 'updated_rows', 'error_rows', 'errors', 'user_id',
        'imported_at', 'group_uuid', 'detection',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'detection' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
