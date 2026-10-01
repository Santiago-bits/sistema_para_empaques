<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $fillable = [
        'key', 'prefix', 'padding', 'next_number',
    ];
}
