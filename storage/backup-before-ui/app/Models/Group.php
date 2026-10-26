<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'currency', 'timezone', 'status'])]
class Group extends Model
{
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
