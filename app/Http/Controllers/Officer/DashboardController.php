<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\PaymentIntent;
use App\Services\Balances\BalanceService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BalanceService $balances): View
    {
        $groupId = $this->groupId();
        $period = now()->format('Y-m');

        $obligations = Obligation::query()->where('group_id', $groupId)->where('period', $period)->get();
        $duesExpected = (int) $obligations->sum('amount_due');
        $duesCollected = (int) Allocation::query()
            ->where('group_id', $groupId)
            ->where('target_type', 'dues')
            ->whereIn('target_id', $obligations->pluck('id'))
            ->sum('amount');

        $months = collect(range(5, 0))->map(function (int $back) use ($groupId) {
            $month = now()->startOfMonth()->subMonths($back);

            return [
                'label' => $month->locale('sw')->translatedFormat('M'),
                'amount' => (int) Allocation::query()
                    ->where('group_id', $groupId)
                    ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                    ->sum('amount'),
            ];
        });

        $statusCounts = Member::query()->where('group_id', $groupId)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $debts = $balances->debtsByMember($groupId);

        return view('officer.dashboard', [
            'period' => $period,
            'membersActive' => (int) ($statusCounts['active'] ?? 0),
            'membersInactive' => (int) ($statusCounts['inactive'] ?? 0),
            'membersPending' => (int) ($statusCounts['pending'] ?? 0),
            'totalIncome' => (int) Allocation::query()->where('group_id', $groupId)->sum('amount'),
            'totalDebt' => (int) $debts->sum(),
            'debtorCount' => $debts->count(),
            'debtors' => Member::query()->whereIn('id', $debts->keys()->take(6))->get()
                ->sortByDesc(fn (Member $m) => $debts[$m->id])
                ->map(fn (Member $m) => [$m, $debts[$m->id]]),
            'duesExpected' => $duesExpected,
            'duesCollected' => $duesCollected,
            'groupSavings' => $balances->groupSavings($groupId),
            'pendingCount' => PaymentIntent::query()->where('group_id', $groupId)->where('status', 'pending')->count(),
            'pendingAmount' => (int) PaymentIntent::query()->where('group_id', $groupId)->where('status', 'pending')->sum('amount'),
            'pending' => PaymentIntent::query()->with('creditedMember')->where('group_id', $groupId)->where('status', 'pending')->latest()->take(5)->get(),
            'recent' => PaymentIntent::query()->with('creditedMember')->where('group_id', $groupId)->where('status', 'succeeded')->latest('updated_at')->take(5)->get(),
            'events' => ContributionEvent::query()->where('group_id', $groupId)->where('status', 'active')->latest()->take(4)->get()
                ->map(fn ($event) => [$event, $balances->allocatedTo('event', $event->id)]),
            'months' => $months,
        ]);
    }
}
