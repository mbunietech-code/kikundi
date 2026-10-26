<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'member_id', 'name', 'relationship', 'status'])]
class Dependant extends Model
{
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
