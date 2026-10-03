<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea de un DTV-e: especie, variedad, cantidad, unidad y kilos. */
class DtvLine extends Model
{
    protected $fillable = ['dtv_document_id', 'species', 'variety_id', 'variety_name', 'quantity', 'unit', 'kg_per_unit', 'kg_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'kg_per_unit' => 'decimal:2', 'kg_total' => 'decimal:2'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DtvDocument::class, 'dtv_document_id');
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class);
    }

    public function varietyLabel(): string
    {
        return $this->variety?->name ?? (string) $this->variety_name;
    }
}
