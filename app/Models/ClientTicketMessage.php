<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTicketMessage extends Model
{
    protected $fillable = ['client_ticket_id', 'user_id', 'author', 'body', 'from_developer', 'remote_reply_id', 'delivered_at'];

    protected function casts(): array
    {
        return ['from_developer' => 'boolean', 'delivered_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ClientTicket::class, 'client_ticket_id');
    }
}
