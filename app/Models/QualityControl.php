<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityControl extends Model
{
    use Auditable;

    public const RESULTS = ['approved' => 'Aprobado', 'rejected' => 'Rechazado', 'observed' => 'Observado'];

    protected $fillable = [
        'crate_id', 'lot_id', 'pallet_id', 'result', 'grade', 'caliber', 'ripeness', 'damage_pct',
        'bruise_pct', 'rot_pct', 'reject_pct', 'defects', 'notes', 'user_id', 'controlled_at',
    ];

    protected function casts(): array
    {
        return [
            'controlled_at' => 'datetime',
            'damage_pct' => 'decimal:2',
            'bruise_pct' => 'decimal:2',
            'rot_pct' => 'decimal:2',
            'reject_pct' => 'decimal:2',
        ];
    }

    public function crate(): BelongsTo
    {
        return $this->belongsTo(Crate::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rejects(): HasMany
    {
        return $this->hasMany(Reject::class);
    }
}
