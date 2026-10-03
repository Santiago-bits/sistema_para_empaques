<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Rendimiento de un insumo (por ejemplo un tambor de cera): desde qué fecha hasta qué fecha se usó y cuántos
 * bultos se empacaron en ese período. Los bultos se cuentan solos con la producción registrada (o se cargan a mano).
 */
class SupplyYield extends Model
{
    use Auditable;

    protected $fillable = ['supply_id', 'name', 'started_on', 'ended_on', 'quantity_used', 'unit', 'packages_manual', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date', 'quantity_used' => 'decimal:2', 'packages_manual' => 'integer'];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    /** Bultos empacados en el período (producción registrada, sin anulados), salvo que se hayan cargado a mano. */
    public function packages(): int
    {
        if ($this->packages_manual !== null) {
            return $this->packages_manual;
        }

        return (int) DB::table('production_records')
            ->whereNull('voided_at')
            ->where('recorded_at', '>=', $this->started_on->copy()->startOfDay())
            ->where('recorded_at', '<=', ($this->ended_on ?? today())->copy()->endOfDay())
            ->count();
    }

    /** Bultos por unidad usada (p. ej. bultos por litro de cera). */
    public function packagesPerUnit(): ?float
    {
        return $this->quantity_used && (float) $this->quantity_used > 0 ? round($this->packages() / (float) $this->quantity_used, 1) : null;
    }
}
