<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    use Auditable;

    public const TYPES = ['preventive' => 'Preventivo', 'corrective' => 'Correctivo', 'emergency' => 'Emergencia'];

    protected $fillable = [
        'machine_id', 'type', 'date', 'technician', 'cost', 'parts_used', 'notes', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
