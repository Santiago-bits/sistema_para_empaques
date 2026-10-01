<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use Auditable;

    protected $fillable = [
        'key', 'name', 'description', 'enabled', 'is_core', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_core' => 'boolean',
        ];
    }
}
