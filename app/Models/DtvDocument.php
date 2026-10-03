<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DTV-e (Documento de Tránsito Vegetal electrónico de SENASA): ingreso o egreso de fruta, con una línea por
 * especie/variedad. Es el registro que antes se llevaba en la hoja «DTV-e» del Excel.
 */
class DtvDocument extends Model
{
    use Auditable, BelongsToWarehouse, SoftDeletes;

    public const DIRECTIONS = ['in' => 'Ingreso', 'out' => 'Egreso'];

    public const DOC_TYPES = ['EMP-CTC' => 'EMP-CTC', 'PROD-EMP' => 'PROD-EMP', 'EMP-EMP' => 'EMP-EMP', 'EMP-MER' => 'EMP-MER', 'OTRO' => 'Otro'];

    protected $fillable = [
        'warehouse_id', 'date', 'direction', 'number', 'doc_type', 'issuer', 'establishment', 'recipient', 'destination',
        'transport', 'notes', 'load_id', 'lot_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DtvLine::class)->orderBy('id');
    }

    public function relatedLoad(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function directionLabel(): string
    {
        return self::DIRECTIONS[$this->direction] ?? $this->direction;
    }

    /** Kilos con signo: los egresos restan (como en la planilla). */
    public function signedKg(): float
    {
        $kg = (float) $this->lines->sum('kg_total');

        return $this->direction === 'out' ? -$kg : $kg;
    }
}
