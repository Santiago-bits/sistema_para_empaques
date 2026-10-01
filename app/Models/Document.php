<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use Auditable, SoftDeletes;

    public const TYPES = [
        'remito' => 'Remito',
        'invoice' => 'Factura',
        'order' => 'Orden',
        'certificate' => 'Certificado',
        'transport' => 'Documentación de transporte',
        'receipt' => 'Comprobante',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'documentable_type', 'documentable_id', 'type', 'title', 'path', 'original_name', 'mime', 'size',
        'uploaded_by',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
