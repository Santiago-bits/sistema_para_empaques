<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producer extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'cuit', 'phone', 'email', 'address', 'locality', 'province', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class);
    }
}
