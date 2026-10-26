<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'member_id', 'from_status', 'to_status', 'reason', 'reason_params', 'changed_by'])]
class MemberStatusChange extends Model
{
    protected function casts(): array
    {
        return ['reason_params' => 'array'];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
