<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Incident extends Model
{
    use Auditable, HasStateHistory;

    public const PRIORITIES = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'];

    public const STATUSES = ['open' => 'Abierto', 'in_progress' => 'En curso', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];

    public const TYPES = [
        'missing_crate' => 'Cajón faltante',
        'weight_error' => 'Error de peso',
        'documentation' => 'Problema de documentación',
        'transport' => 'Problema de transporte',
        'damaged_product' => 'Producto dañado',
        'load_error' => 'Error de carga',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'number', 'type', 'occurred_at', 'area', 'description', 'priority', 'status', 'resolution',
        'related_type', 'related_id', 'reported_by', 'responsible_id', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
