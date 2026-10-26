<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\DuesRule;
use App\Models\Obligation;
use App\Services\Balances\BalanceService;
use App\Services\Dues\DuesService;
use App\Services\Reminders\ReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DuesController extends Controller
{
    public function index(Request $request, DuesService $dues, BalanceService $balances): View
    {
        $groupId = $this->groupId();
        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('period')) ? $request->query('period') : now()->format('Y-m');

        $rows = Obligation::query()->with('member')->where('group_id', $groupId)->where('period', $period)
            ->get()
            ->map(function (Obligation $o) use ($balances) {
                $paid = $balances->allocatedTo('dues', $o->id);

                return [
                    'obligation' => $o,
                    'paid' => $paid,
                    'outstanding' => max(0, $o->amount_due - $paid),
                    'state' => $paid >= $o->amount_due ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                ];
            })
            ->sortBy(fn ($row) => $row['obligation']->member->member_number)
            ->values();

        $periods = Obligation::query()->where('group_id', $groupId)->distinct()->orderByDesc('period')->pluck('period')
            ->push(now()->format('Y-m'))->unique()->sortDesc()->values();

        return view('officer.dues.index', [
            'period' => $period,
            'periods' => $periods,
            'rows' => $rows,
            'rule' => $dues->currentRule($groupId),
            'history' => DuesRule::query()->where('group_id', $groupId)->latest('effective_from')->take(5)->get(),
            'expected' => $rows->sum(fn ($r) => $r['obligation']->amount_due),
            'collected' => $rows->sum('paid'),
            'deadline' => app(ReminderService::class)->duesDeadline(CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')),
        ]);
    }

    public function storeRule(Request $request, DuesService $dues): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
            'effective_from' => ['required', 'date'],
        ], [], ['amount' => __('kiasi'), 'effective_from' => __('tarehe ya kuanza')]);

        $dues->changeRule($this->groupId(), (int) $data['amount'], CarbonImmutable::parse($data['effective_from']));

        return back()->with('status', __('Kiwango kipya cha ada kimehifadhiwa.'));
    }

    public function generate(Request $request, DuesService $dues): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);

        if (! $dues->currentRule($this->groupId(), CarbonImmutable::createFromFormat('Y-m-d', $data['period'].'-01'))) {
            return back()->with('error', __('Weka kiwango cha ada kwanza.'));
        }

        $count = $dues->generate($this->groupId(), $data['period']);

        return redirect()->route('dues.index', ['period' => $data['period']])
            ->with('status', $count ? __('Madeni :count ya ada ya :period yameundwa.', ['count' => $count, 'period' => $data['period']]) : __('Madeni ya mwezi huu yalishaundwa kwa wanachama wote.'));
    }
}
