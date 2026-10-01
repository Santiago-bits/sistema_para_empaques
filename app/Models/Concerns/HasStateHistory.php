<?php

namespace App\Models\Concerns;

use App\Models\StateHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStateHistory
{
    public function stateHistories(): MorphMany
    {
        return $this->morphMany(StateHistory::class, 'stateful')->orderBy('created_at')->orderBy('id');
    }
}
