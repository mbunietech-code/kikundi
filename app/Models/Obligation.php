<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'member_id', 'dues_rule_id', 'period', 'amount_due', 'currency'])]
class Obligation extends Model
{
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
