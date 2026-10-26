<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'payment_intent_id', 'provider', 'provider_transaction_id', 'amount', 'currency', 'verification_method', 'verified_by', 'verified_at', 'status'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
