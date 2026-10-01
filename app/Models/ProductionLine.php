<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;

class ProductionLine extends Model
{
    use Auditable, BelongsToWarehouse;

    protected $fillable = [
        'warehouse_id', 'code', 'name', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
