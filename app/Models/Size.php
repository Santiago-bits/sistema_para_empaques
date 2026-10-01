<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'sort', 'active',
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
