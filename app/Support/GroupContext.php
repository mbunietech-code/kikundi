<?php

namespace App\Support;

use App\Models\Group;
use App\Models\User;

/**
 * Resolves the group an officer works in. During the single-group pilot a
 * system admin without a group works in the configured pilot group.
 */
class GroupContext
{
    public static function for(?User $user): Group
    {
        $groupId = $user?->group_id ?? config('beem.ussd_group_id');

        // A fresh install has no group yet; create the pilot group on first use.
        return Group::query()->find($groupId)
            ?? Group::query()->orderBy('id')->first()
            ?? Group::query()->create(['name' => 'Kikundi']);
    }

    public static function id(): int
    {
        return self::for(auth()->user())->id;
    }
}
