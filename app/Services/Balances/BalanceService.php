<?php

namespace App\Services\Balances;

use App\Models\Allocation;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\MembershipFee;
use App\Models\Obligation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function feeOutstanding(MembershipFee $fee): int
    {
        return max(0, $fee->amount_due - $this->allocatedTo('membership_fee', $fee->id));
    }

    /**
     * Unpaid dues across the group in one query, for dashboards and the
     * membership check.
     *
     * @return Collection<int, object{member_id: int, period: string, outstanding: int}>
     */
    public function unpaidDues(int $groupId): Collection
    {
        $paid = Allocation::query()
            ->selectRaw('target_id, SUM(amount) as paid')
            ->where('group_id', $groupId)
            ->where('target_type', 'dues')
            ->groupBy('target_id');

        return Obligation::query()
            ->leftJoinSub($paid, 'paid', 'paid.target_id', '=', 'obligations.id')
            ->where('obligations.group_id', $groupId)
            ->whereRaw('obligations.amount_due > COALESCE(paid.paid, 0)')
            ->get(['obligations.member_id', 'obligations.period', DB::raw('obligations.amount_due - COALESCE(paid.paid, 0) as outstanding')])
            ->map(fn ($row) => (object) ['member_id' => (int) $row->member_id, 'period' => $row->period, 'outstanding' => (int) $row->outstanding]);
    }

    /**
     * What each active member still owes to active events that ask a fixed
     * amount per member. The beneficiary is not expected to contribute.
     *
     * @return Collection<int, object{event_id: int, member_id: int, outstanding: int}>
     */
    public function eventDebts(int $groupId, ?int $eventId = null): Collection
    {
        $events = ContributionEvent::query()
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->where('amount_per_member', '>', 0)
            ->when($eventId, fn ($q) => $q->whereKey($eventId))
            ->get();

        if ($events->isEmpty()) {
            return collect();
        }

        $paid = Allocation::query()
            ->where('group_id', $groupId)
            ->where('target_type', 'event')
            ->whereIn('target_id', $events->modelKeys())
            ->selectRaw('target_id, credited_member_id, SUM(amount) as paid')
            ->groupBy('target_id', 'credited_member_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->target_id.':'.$row->credited_member_id => (int) $row->paid]);

        $members = Member::query()->where('group_id', $groupId)->where('status', 'active')->pluck('id');

        return $events->flatMap(fn (ContributionEvent $event) => $members
            ->reject(fn ($id) => $id === $event->beneficiary_member_id)
            ->map(fn ($id) => (object) [
                'event_id' => $event->id,
                'member_id' => $id,
                'outstanding' => max(0, $event->amount_per_member - ($paid[$event->id.':'.$id] ?? 0)),
            ]))
            ->filter(fn ($row) => $row->outstanding > 0)
            ->values();
    }

    /**
     * Total each member still owes in dues, membership fees and events.
     *
     * @return Collection<int, int> member id => amount
     */
    public function debtsByMember(int $groupId): Collection
    {
        $debts = collect();

        foreach ($this->debtBreakdowns($groupId) as $memberId => $parts) {
            $debts[$memberId] = array_sum($parts);
        }

        return $debts->sortDesc();
    }

    /**
     * @return Collection<int, array{dues: int, fees: int, events: int}> member id => amounts owed
     */
    public function debtBreakdowns(int $groupId): Collection
    {
        $debts = collect();
        $add = function (int $memberId, string $part, int $amount) use ($debts) {
            $row = $debts[$memberId] ?? ['dues' => 0, 'fees' => 0, 'events' => 0];
            $row[$part] += $amount;
            $debts[$memberId] = $row;
        };

        $this->unpaidDues($groupId)->each(fn ($row) => $add($row->member_id, 'dues', $row->outstanding));

        MembershipFee::query()->where('group_id', $groupId)->get()->each(function (MembershipFee $fee) use ($add) {
            if (($owed = $this->feeOutstanding($fee)) > 0) {
                $add($fee->member_id, 'fees', $owed);
            }
        });

        $this->eventDebts($groupId)->each(fn ($row) => $add($row->member_id, 'events', $row->outstanding));

        return $debts;
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
