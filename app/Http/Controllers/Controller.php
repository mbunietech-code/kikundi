<?php

namespace App\Http\Controllers;

use App\Support\GroupContext;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    protected function groupId(): int
    {
        return GroupContext::id();
    }

    /** Hide records of other groups as if they did not exist. */
    protected function ensureGroup(Model $model): void
    {
        abort_unless((int) $model->group_id === $this->groupId(), 404);
    }
}
