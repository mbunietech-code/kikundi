<?php

namespace App\Services\Balances;

use App\Models\Allocation;
use App\Models\Member;
use App\Models\Obligation;
use Illuminate\Support\Collection;

/**
 * Balances are always derived from posted allocations, never stored.
 */
class BalanceService
{
    public function allocatedTo(string $targetType, int $targetId): int
    {
        return (int) Allocation::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->sum('amount');
    }

    public function outstandingFor(Obligation $obligation): int
    {
        return max(0, $obligation->amount_due - $this->allocatedTo('dues', $obligation->id));
    }

    /**
     * @return Collection<int, array{obligation: Obligation, outstanding: int}>
     */
    public function unpaidObligations(Member $member, int $limit = 5): Collection
    {
        return $member->obligations()
            ->orderBy('period')
            ->get()
            ->map(fn (Obligation $obligation) => ['obligation' => $obligation, 'outstanding' => $this->outstandingFor($obligation)])
            ->filter(fn (array $row) => $row['outstanding'] > 0)
            ->take($limit)
            ->values();
    }

    public function memberSavings(Member $member): int
    {
        return (int) Allocation::query()
            ->where('group_id', $member->group_id)
            ->where('target_type', 'savings')
            ->where('credited_member_id', $member->id)
            ->sum('amount');
    }

    public function groupSavings(int $groupId): int
    {
        return (int) Allocation::query()
            ->where('group_id', $groupId)
            ->where('target_type', 'savings')
            ->sum('amount');
    }
}
