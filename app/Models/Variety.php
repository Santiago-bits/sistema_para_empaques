<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variety extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'species', 'color', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class);
    }
}
