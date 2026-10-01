<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemitoItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'remito_id', 'variety_id', 'size_id', 'crates', 'kg',
    ];

    protected function casts(): array
    {
        return [
            'kg' => 'decimal:2',
        ];
    }

    public function remito(): BelongsTo
    {
        return $this->belongsTo(Remito::class);
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }
}
