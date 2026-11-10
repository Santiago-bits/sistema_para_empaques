<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use BelongsToWarehouse;

    protected $fillable = [
        'key', 'prefix', 'padding', 'next_number',
    ];
}
