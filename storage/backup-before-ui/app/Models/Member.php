<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['pin_hash'])]
#[Fillable(['group_id', 'user_id', 'member_number', 'full_name', 'phone', 'address', 'joined_at', 'status', 'pin_hash', 'pin_failed_attempts', 'pin_locked_until'])]
class Member extends Model
{
    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'pin_locked_until' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(Dependant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
