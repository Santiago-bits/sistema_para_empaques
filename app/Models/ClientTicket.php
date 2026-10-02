<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pedido de soporte de un empaque, recibido en el Panel General. */
class ClientTicket extends Model
{
    use Auditable;

    protected $fillable = [
        'license_id', 'remote_number', 'subject', 'description', 'priority', 'status', 'requester', 'created_remote_at',
        'last_message_at', 'status_pending',
    ];

    protected function casts(): array
    {
        return ['created_remote_at' => 'datetime', 'last_message_at' => 'datetime', 'status_pending' => 'boolean'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ClientTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }
}
