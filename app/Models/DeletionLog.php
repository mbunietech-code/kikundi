<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'user_id', 'kind', 'subject', 'amount', 'details', 'reason'])]
class DeletionLog extends Model
{
    protected function casts(): array
    {
        return ['details' => 'array', 'amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
