<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Auditable, HasStateHistory, SoftDeletes;

    public const VOUCHER_TYPES = [
        1 => 'Factura A',
        6 => 'Factura B',
        11 => 'Factura C',
        3 => 'Nota de crédito A',
        8 => 'Nota de crédito B',
        13 => 'Nota de crédito C',
    ];

    protected $fillable = [
        'client_id', 'remito_id', 'load_id', 'voucher_type', 'point_of_sale', 'number', 'issued_on',
        'currency', 'exchange_rate', 'net_amount', 'vat_amount', 'total_amount', 'status', 'cae',
        'cae_expires_on', 'arca_mode', 'attempts', 'last_error', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'cae_expires_on' => 'date',
            'status' => InvoiceStatus::class,
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function remito(): BelongsTo
    {
        return $this->belongsTo(Remito::class);
    }

    public function load(): BelongsTo
    {
        return $this->belongsTo(Load::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function arcaRecords(): HasMany
    {
        return $this->hasMany(ArcaRecord::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function formattedNumber(): string
    {
        if ($this->number === null) {
            return 'Borrador #'.$this->id;
        }

        return sprintf('%04d-%08d', $this->point_of_sale, $this->number);
    }

    public function voucherLabel(): string
    {
        return self::VOUCHER_TYPES[$this->voucher_type] ?? (string) $this->voucher_type;
    }
}
