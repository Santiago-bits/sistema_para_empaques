<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use Auditable;

    public const STATUSES = [
        'open' => 'Abierto',
        'review' => 'En revisión',
        'development' => 'En desarrollo',
        'resolved' => 'Resuelto',
        'closed' => 'Cerrado',
    ];

    protected $fillable = [
        'number', 'subject', 'description', 'priority', 'status', 'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)->orderBy('created_at');
    }
}
