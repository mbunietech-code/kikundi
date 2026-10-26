<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'event_type', 'title', 'beneficiary_member_id', 'description', 'amount_per_member', 'target_amount', 'starts_at', 'ends_at', 'status'])]
class ContributionEvent extends Model
{
    public const TYPES = ['illness' => 'Ugonjwa', 'bereavement' => 'Msiba', 'wedding' => 'Harusi', 'development' => 'Maendeleo', 'other' => 'Mengineyo'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount_per_member' => 'integer',
            'target_amount' => 'integer',
        ];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'beneficiary_member_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->event_type] ?? $this->event_type;
    }
}
