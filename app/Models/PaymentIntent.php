<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['group_id', 'payer_member_id', 'credited_member_id', 'target_type', 'target_id', 'amount', 'currency', 'reference', 'idempotency_key', 'channel', 'payer_msisdn', 'status', 'expires_at'])]
class PaymentIntent extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'payer_member_id');
    }

    public function creditedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'credited_member_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
