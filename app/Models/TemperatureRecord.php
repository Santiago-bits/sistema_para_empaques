<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemperatureRecord extends Model
{
    use BelongsToWarehouse;

    public $timestamps = false;

    protected $fillable = [
        'cold_room_id', 'temperature', 'humidity', 'source', 'out_of_range', 'user_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'out_of_range' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    public function coldRoom(): BelongsTo
    {
        return $this->belongsTo(ColdRoom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
