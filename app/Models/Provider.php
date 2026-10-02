<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name', 'cuit', 'contact', 'phone', 'email', 'address', 'products', 'active', 'locality', 'province', 'tax_condition', 'cbu',
        'bank_alias', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }
}
