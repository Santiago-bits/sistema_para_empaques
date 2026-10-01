<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadCrate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'load_id', 'crate_id', 'active_crate_id', 'added_by', 'added_at', 'removed_at', 'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function loadRecord(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    public function crate(): BelongsTo
    {
        return $this->belongsTo(Crate::class);
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
