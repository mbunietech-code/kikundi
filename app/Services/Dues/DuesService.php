<?php

namespace App\Services\Dues;

use App\Models\DuesRule;
use App\Models\Member;
use App\Models\Obligation;
use Carbon\CarbonImmutable;

class DuesService
{
    public function currentRule(int $groupId, ?CarbonImmutable $on = null): ?DuesRule
    {
        $on ??= CarbonImmutable::now();

        return DuesRule::query()
            ->where('group_id', $groupId)
            ->whereDate('effective_from', '<=', $on)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))
            ->latest('effective_from')
            ->first();
    }

    /**
     * Start a new dues amount from a date. The previous rule is closed the
     * day before, so historical obligations keep their original amount.
     */
    public function changeRule(int $groupId, int $amount, CarbonImmutable $from): DuesRule
    {
        DuesRule::query()
            ->where('group_id', $groupId)
            ->whereNull('effective_to')
            ->whereDate('effective_from', '<', $from)
            ->update(['effective_to' => $from->subDay()->toDateString()]);

        return DuesRule::query()->create([
            'group_id' => $groupId,
            'amount' => $amount,
            'effective_from' => $from->toDateString(),
        ]);
    }

    /**
     * Create the month's obligation for every active member. Safe to run
     * more than once: existing obligations are left untouched.
     *
     * @return int number of obligations created
     */
    public function generate(int $groupId, string $period): int
    {
        $rule = $this->currentRule($groupId, CarbonImmutable::createFromFormat('Y-m-d', $period.'-01'));

        if (! $rule) {
            return 0;
        }

        $created = 0;

        Member::query()->where('group_id', $groupId)->where('status', 'active')->each(function (Member $member) use ($rule, $period, &$created) {
            $obligation = Obligation::query()->firstOrCreate(
                ['member_id' => $member->id, 'period' => $period],
                ['group_id' => $member->group_id, 'dues_rule_id' => $rule->id, 'amount_due' => $rule->amount, 'currency' => $rule->currency],
            );

            $created += $obligation->wasRecentlyCreated ? 1 : 0;
        });

        return $created;
    }
}
