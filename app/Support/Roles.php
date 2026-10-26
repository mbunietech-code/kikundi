<?php

namespace App\Support;

class Roles
{
    public const LABELS = [
        'system_admin' => 'Msimamizi wa mfumo',
        'chair' => 'Mwenyekiti',
        'secretary' => 'Katibu',
        'treasurer' => 'Mweka hazina',
    ];

    /** Roles allowed into the officer portal. */
    public const OFFICERS = ['system_admin', 'chair', 'secretary', 'treasurer'];

    /** The chair sees everything but changes nothing. */
    public const MEMBERSHIP_VIEW = ['system_admin', 'chair', 'secretary'];

    public const MEMBERSHIP = ['system_admin', 'secretary'];

    public const FINANCE_VIEW = ['system_admin', 'chair', 'treasurer'];

    public const FINANCE = ['system_admin', 'treasurer'];

    /** Contribution events are run by either the secretary or the treasurer. */
    public const EVENTS = ['system_admin', 'secretary', 'treasurer'];

    public static function label(?string $role): string
    {
        return self::LABELS[$role] ?? 'Mwanachama';
    }
}
