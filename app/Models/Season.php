<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use Auditable;

    protected $fillable = [
        'name', 'starts_on', 'ends_on', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->where('is_current', true)->first();
    }
}
