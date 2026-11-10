<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketReply extends Model
{
    use BelongsToWarehouse;

    protected $fillable = [
        'support_ticket_id', 'user_id', 'body', 'from_developer', 'central_id', 'central_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'from_developer' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
