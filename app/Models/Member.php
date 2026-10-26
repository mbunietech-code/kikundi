<?php

namespace App\Models;

use App\Support\Locale;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['pin_hash'])]
#[Fillable(['group_id', 'user_id', 'member_number', 'full_name', 'phone', 'locale', 'address', 'photo_path', 'joined_at', 'status', 'status_changed_at', 'pin_hash', 'pin_failed_attempts', 'pin_locked_until'])]
class Member extends Model implements AuthenticatableContract
{
    use Authenticatable;

    /** The USSD PIN doubles as the portal password. */
    public function getAuthPasswordName(): string
    {
        return 'pin_hash';
    }

    /** Members have no remember-me column. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'pin_locked_until' => 'datetime',
            'status_changed_at' => 'datetime',
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

    public function fees(): HasMany
    {
        return $this->hasMany(MembershipFee::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(MemberStatusChange::class);
    }

    /** Language for this member's SMS and USSD. */
    public function preferredLocale(): string
    {
        return Locale::valid($this->locale) ? $this->locale : Locale::default();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
