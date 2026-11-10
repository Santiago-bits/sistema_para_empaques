<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Reason extends Model
{
    use BelongsToWarehouse;

    use Auditable;

    public const TYPES = ['reject' => 'Rechazo', 'stoppage' => 'Parada', 'incident' => 'Incidente'];

    protected $fillable = [
        'type', 'code', 'name', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
