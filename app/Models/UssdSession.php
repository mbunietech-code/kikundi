<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['session_id', 'msisdn', 'operator', 'member_id', 'state', 'data', 'authenticated', 'step', 'ended_at'])]
class UssdSession extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'authenticated' => 'boolean',
            'ended_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
