<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'member_id', 'kind', 'amount_due', 'currency'])]
class MembershipFee extends Model
{
    public const KINDS = ['entry' => 'Kiingilio', 'reinstatement' => 'Kiingilio cha kurejea'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
