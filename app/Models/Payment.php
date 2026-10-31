<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['receipt_number', 'group_id', 'payment_intent_id', 'provider', 'provider_transaction_id', 'amount', 'currency', 'verification_method', 'verified_by', 'verified_at', 'status'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    /** Unique, never reused: built from the row id once it exists. */
    public static function receiptNumberFor(int $id): string
    {
        return sprintf('RCT%06d', $id);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
