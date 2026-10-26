<?php

namespace App\Services\Reports;

use App\Http\Controllers\Officer\MemberController;
use App\Http\Controllers\Officer\PaymentController;
use App\Models\Allocation;
use App\Models\Group;
use App\Models\Member;
use App\Models\MembershipFee;
use App\Models\Obligation;
use App\Services\Balances\BalanceService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the group reports. Amounts come from posted allocations dated by
 * when the payment was verified; debts are worked out as at the period end.
 */
class ReportService
{
    public function __construct(private BalanceService $balances) {}

    public const TYPES = [
        'income' => ['Mapato ya kikundi', 'Jumla ya mapato kwa aina, kwa mwezi na kwa njia ya malipo.'],
        'payers' => ['Waliolipa', 'Kila mwanachama aliyelipa ndani ya kipindi na kiasi alicholipa.'],
        'debtors' => ['Wadaiwa', 'Wanachama wanaodaiwa ada au kiingilio mwishoni mwa kipindi.'],
        'member' => ['Ripoti ya mwanachama', 'Ada, viingilio na malipo ya mwanachama mmoja.'],
    ];

    public const TARGETS = ['dues' => 'Ada', 'savings' => 'Akiba', 'event' => 'Michango', 'membership_fee' => 'Viingilio'];

    public function build(string $type, Group $group, ReportPeriod $period, ?Member $member = null): Report
    {
        return match ($type) {
            'income' => $this->income($group, $period),
            'payers' => $this->payers($group, $period),
            'debtors' => $this->debtors($group, $period),
            'member' => $this->member($group, $period, $member),
        };
    }

    public function income(Group $group, ReportPeriod $period): Report
    {
        $rows = $this->allocations($group->id, $period->start, $period->end)
            ->join('payments', 'payments.id', '=', 'allocations.payment_id')
            ->get(['allocations.target_type', 'allocations.amount', 'allocations.created_at', 'payments.provider']);

        $byType = collect(self::TARGETS)->map(fn ($label, $key) => [
            'type' => $label,
            'count' => $rows->where('target_type', $key)->count(),
            'amount' => (int) $rows->where('target_type', $key)->sum('amount'),
        ])->values()->all();

        $byMonth = collect($period->months())->map(function (CarbonImmutable $month) use ($rows, $period) {
            $inMonth = $rows->filter(fn ($r) => $r->created_at->format('Y-m') === $month->format('Y-m'));

            return ['month' => $period->monthName($month).' '.$month->year]
                + collect(self::TARGETS)->mapWithKeys(fn ($l, $key) => [$key => (int) $inMonth->where('target_type', $key)->sum('amount')])->all()
                + ['total' => (int) $inMonth->sum('amount')];
        })->all();

        $byMethod = $rows->groupBy('provider')->map(fn ($group, $provider) => [
            'method' => PaymentController::METHODS[$provider] ?? Str::headline($provider),
            'count' => $group->count(),
            'amount' => (int) $group->sum('amount'),
        ])->sortByDesc('amount')->values()->all();

        $total = (int) $rows->sum('amount');
        $moneyColumns = collect(self::TARGETS)->map(fn ($label) => [$label, 'money'])->all();

        return new Report(
            __('Ripoti ya mapato'),
            $group->name.' · '.$period->label(),
            $this->summary([
                'Jumla ya mapato' => Money::format($total, $group->currency),
                'Idadi ya malipo' => number_format($rows->count()),
                'Ada' => Money::format($byType[0]['amount'], $group->currency),
                'Akiba' => Money::format($byType[1]['amount'], $group->currency),
            ]),
            [
                $this->section('Kwa aina ya malipo', ['type' => ['Aina', 'text'], 'count' => ['Idadi', 'number'], 'amount' => ['Kiasi', 'money']],
                    $byType, ['type' => 'Jumla', 'count' => $rows->count(), 'amount' => $total]),
                $this->section('Kwa mwezi', ['month' => ['Mwezi', 'text']] + $moneyColumns + ['total' => ['Jumla', 'money']],
                    $byMonth, ['month' => 'Jumla'] + collect(self::TARGETS)->mapWithKeys(fn ($l, $key) => [$key => (int) $rows->where('target_type', $key)->sum('amount')])->all() + ['total' => $total]),
                $this->section('Kwa njia ya malipo', ['method' => ['Njia', 'text'], 'count' => ['Idadi', 'number'], 'amount' => ['Kiasi', 'money']],
                    $byMethod, ['method' => 'Jumla', 'count' => $rows->count(), 'amount' => $total]),
            ],
            'mapato-'.$period->slug(),
        );
    }

    public function payers(Group $group, ReportPeriod $period): Report
    {
        $rows = $this->allocations($group->id, $period->start, $period->end)->get(['credited_member_id', 'target_type', 'amount']);
        $members = Member::query()->whereIn('id', $rows->pluck('credited_member_id')->unique())->get()->keyBy('id');

        $table = $rows->groupBy('credited_member_id')->map(function ($paid, $memberId) use ($members) {
            $member = $members[$memberId];

            return ['number' => $member->member_number, 'name' => $member->full_name, 'phone' => '+'.$member->phone]
                + collect(self::TARGETS)->mapWithKeys(fn ($l, $key) => [$key => (int) $paid->where('target_type', $key)->sum('amount')])->all()
                + ['total' => (int) $paid->sum('amount')];
        })->sortBy('number')->values();

        $active = Member::query()->where('group_id', $group->id)->where('status', 'active')->count();
        $paidDues = $rows->where('target_type', 'dues')->pluck('credited_member_id')->unique()->count();

        return new Report(
            __('Ripoti ya waliolipa'),
            $group->name.' · '.$period->label(),
            $this->summary([
                'Waliolipa' => number_format($table->count()),
                'Walilipa ada' => __(':paid kati ya :active hai', ['paid' => $paidDues, 'active' => $active]),
                'Jumla iliyolipwa' => Money::format((int) $rows->sum('amount'), $group->currency),
            ]),
            [
                $this->section('Waliolipa', ['number' => ['Namba', 'text'], 'name' => ['Jina', 'text'], 'phone' => ['Simu', 'text']]
                    + collect(self::TARGETS)->map(fn ($label) => [$label, 'money'])->all() + ['total' => ['Jumla', 'money']],
                    $table->all(), ['number' => '', 'name' => 'Jumla', 'phone' => '']
                    + collect(self::TARGETS)->mapWithKeys(fn ($l, $key) => [$key => (int) $rows->where('target_type', $key)->sum('amount')])->all()
                    + ['total' => (int) $rows->sum('amount')]),
            ],
            'waliolipa-'.$period->slug(),
        );
    }

    public function debtors(Group $group, ReportPeriod $period): Report
    {
        $asOf = $period->end->min(CarbonImmutable::now());
        $dues = $this->unpaidDuesAsOf($group->id, $asOf);
        $fees = $this->unpaidFeesAsOf($group->id, $asOf);
        // Event debts depend on who is active, which is only known for today.
        $events = $asOf->isToday() ? $this->balances->eventDebts($group->id)->groupBy('member_id')->map->sum('outstanding') : collect();

        $memberIds = $dues->pluck('member_id')->merge($fees->keys())->merge($events->keys())->unique();
        $members = Member::query()->whereIn('id', $memberIds)->where('status', '!=', 'departed')->orderBy('member_number')->get();

        $table = $members->map(fn (Member $m) => [
            'number' => $m->member_number,
            'name' => $m->full_name,
            'phone' => '+'.$m->phone,
            'status' => MemberController::STATUSES[$m->status] ?? $m->status,
            'months' => $dues->where('member_id', $m->id)->count(),
            'dues' => (int) $dues->where('member_id', $m->id)->sum('outstanding'),
            'fees' => (int) ($fees[$m->id] ?? 0),
            'events' => (int) ($events[$m->id] ?? 0),
        ])->map(fn ($row) => $row + ['total' => $row['dues'] + $row['fees'] + $row['events']])
            ->sortByDesc('total')->values();

        return new Report(
            __('Ripoti ya wadaiwa'),
            $group->name.' · '.__('hadi :date', ['date' => $asOf->format('d/m/Y')]),
            $this->summary([
                'Wadaiwa' => number_format($table->count()),
                'Deni la ada' => Money::format($table->sum('dues'), $group->currency),
                'Viingilio na michango' => Money::format($table->sum('fees') + $table->sum('events'), $group->currency),
                'Jumla ya madeni' => Money::format($table->sum('total'), $group->currency),
            ]),
            [
                $this->section('Wadaiwa', [
                    'number' => ['Namba', 'text'], 'name' => ['Jina', 'text'], 'phone' => ['Simu', 'text'], 'status' => ['Hali', 'text'],
                    'months' => ['Miezi', 'number'], 'dues' => ['Deni la ada', 'money'], 'fees' => ['Kiingilio', 'money'], 'events' => ['Michango', 'money'], 'total' => ['Jumla', 'money'],
                ], $table->all(), ['number' => '', 'name' => 'Jumla', 'phone' => '', 'status' => '', 'months' => $table->sum('months'),
                    'dues' => $table->sum('dues'), 'fees' => $table->sum('fees'), 'events' => $table->sum('events'), 'total' => $table->sum('total')]),
            ],
            'wadaiwa-'.$period->slug(),
        );
    }

    public function member(Group $group, ReportPeriod $period, Member $member): Report
    {
        $asOf = $period->end->min(CarbonImmutable::now());
        [$from, $to] = $period->periods();
        $paidUntil = fn (string $type, int $id) => (int) Allocation::query()
            ->where('target_type', $type)->where('target_id', $id)->where('created_at', '<=', $asOf)->sum('amount');

        $dues = $member->obligations()->whereBetween('period', [$from, $to])->orderBy('period')->get()->map(function (Obligation $o) use ($paidUntil, $period) {
            $paid = $paidUntil('dues', $o->id);
            $month = CarbonImmutable::createFromFormat('Y-m-d', $o->period.'-01');

            return ['month' => $period->monthName($month).' '.$month->year, 'due' => (int) $o->amount_due, 'paid' => $paid,
                'balance' => max(0, $o->amount_due - $paid), 'state' => $paid >= $o->amount_due ? 'Imelipwa' : ($paid > 0 ? 'Sehemu' : 'Haijalipwa')];
        });

        $fees = $member->fees()->where('created_at', '<=', $asOf)->orderBy('id')->get()->map(function (MembershipFee $f) use ($paidUntil) {
            $paid = $paidUntil('membership_fee', $f->id);

            return ['kind' => $f->label(), 'date' => $f->created_at->format('d/m/Y'), 'due' => (int) $f->amount_due, 'paid' => $paid, 'balance' => max(0, $f->amount_due - $paid)];
        });

        $payments = Allocation::query()
            ->join('payments', 'payments.id', '=', 'allocations.payment_id')
            ->leftJoin('payment_intents', 'payment_intents.id', '=', 'payments.payment_intent_id')
            ->where('allocations.credited_member_id', $member->id)
            ->whereBetween('allocations.created_at', [$period->start, $period->end])
            ->orderBy('allocations.created_at')
            ->get(['allocations.created_at', 'allocations.target_type', 'allocations.amount', 'payments.provider', 'payment_intents.reference'])
            ->map(fn ($a) => [
                'date' => $a->created_at->format('d/m/Y'),
                'reference' => $a->reference ?? '',
                'type' => self::TARGETS[$a->target_type] ?? $a->target_type,
                'method' => PaymentController::METHODS[$a->provider] ?? Str::headline($a->provider),
                'amount' => (int) $a->amount,
            ]);

        $owed = (int) $this->unpaidDuesAsOf($group->id, $asOf)->where('member_id', $member->id)->sum('outstanding')
            + (int) ($this->unpaidFeesAsOf($group->id, $asOf)[$member->id] ?? 0);
        $savings = (int) Allocation::query()->where('credited_member_id', $member->id)->where('target_type', 'savings')->where('created_at', '<=', $asOf)->sum('amount');

        $sections = [
            $this->section('Ada kwa mwezi', ['month' => ['Mwezi', 'text'], 'due' => ['Inadaiwa', 'money'], 'paid' => ['Imelipwa', 'money'], 'balance' => ['Salio', 'money'], 'state' => ['Hali', 'text']],
                $dues->all(), ['month' => 'Jumla', 'due' => $dues->sum('due'), 'paid' => $dues->sum('paid'), 'balance' => $dues->sum('balance'), 'state' => '']),
        ];

        if ($fees->isNotEmpty()) {
            $sections[] = $this->section('Viingilio', ['kind' => ['Aina', 'text'], 'date' => ['Tarehe', 'text'], 'due' => ['Inadaiwa', 'money'], 'paid' => ['Imelipwa', 'money'], 'balance' => ['Salio', 'money']],
                $fees->all(), null);
        }

        $sections[] = $this->section('Malipo ndani ya kipindi', ['date' => ['Tarehe', 'text'], 'reference' => ['Kumbukumbu', 'text'], 'type' => ['Aina', 'text'], 'method' => ['Njia', 'text'], 'amount' => ['Kiasi', 'money']],
            $payments->all(), ['date' => 'Jumla', 'reference' => '', 'type' => '', 'method' => '', 'amount' => $payments->sum('amount')]);

        return new Report(
            $member->full_name.' ('.$member->member_number.')',
            $group->name.' · '.$period->label(),
            $this->summary([
                'Hali' => __(MemberController::STATUSES[$member->status] ?? $member->status),
                'Simu' => '+'.$member->phone,
                'Alijiunga' => $member->joined_at?->format('d/m/Y') ?? '—',
                'Amelipa kipindi hiki' => Money::format($payments->sum('amount'), $group->currency),
                __('Deni hadi :date', ['date' => $asOf->format('d/m/Y')]) => Money::format($owed, $group->currency),
                'Akiba yake' => Money::format($savings, $group->currency),
            ]),
            $sections,
            'mwanachama-'.Str::slug($member->member_number).'-'.$period->slug(),
        );
    }

    private function allocations(int $groupId, CarbonImmutable $from, CarbonImmutable $to)
    {
        return Allocation::query()->where('allocations.group_id', $groupId)->whereBetween('allocations.created_at', [$from, $to]);
    }

    /** @return Collection<int, object{member_id: int, outstanding: int}> */
    private function unpaidDuesAsOf(int $groupId, CarbonImmutable $asOf): Collection
    {
        $paid = Allocation::query()->where('group_id', $groupId)->where('target_type', 'dues')->where('created_at', '<=', $asOf)
            ->selectRaw('target_id, SUM(amount) as paid')->groupBy('target_id')->pluck('paid', 'target_id');

        return Obligation::query()->where('group_id', $groupId)->where('period', '<=', $asOf->format('Y-m'))->get(['id', 'member_id', 'amount_due'])
            ->map(fn ($o) => (object) ['member_id' => (int) $o->member_id, 'outstanding' => max(0, $o->amount_due - (int) ($paid[$o->id] ?? 0))])
            ->filter(fn ($o) => $o->outstanding > 0)->values();
    }

    /** @return Collection<int, int> member id => fee amount still owed */
    private function unpaidFeesAsOf(int $groupId, CarbonImmutable $asOf): Collection
    {
        $paid = Allocation::query()->where('group_id', $groupId)->where('target_type', 'membership_fee')->where('created_at', '<=', $asOf)
            ->selectRaw('target_id, SUM(amount) as paid')->groupBy('target_id')->pluck('paid', 'target_id');

        return MembershipFee::query()->where('group_id', $groupId)->where('created_at', '<=', $asOf)->get()
            ->groupBy('member_id')
            ->map(fn ($fees) => (int) $fees->sum(fn ($f) => max(0, $f->amount_due - (int) ($paid[$f->id] ?? 0))))
            ->filter();
    }

    /**
     * Headings and text cells are translated here, once, for screen, PDF and
     * Excel alike. Names and references have no translation and pass through.
     */
    private function section(string $title, array $columns, array $rows, ?array $totals): array
    {
        $translate = fn (array $row) => collect($row)->map(fn ($value, $key) => ($columns[$key][1] ?? null) === 'text' && is_string($value) && $value !== '' ? __($value) : $value)->all();

        return [
            'title' => __($title),
            'columns' => collect($columns)->map(fn ($column) => [__($column[0]), $column[1]])->all(),
            'rows' => array_map($translate, $rows),
            'totals' => $totals ? $translate($totals) : null,
        ];
    }

    /** @param array<string, string> $summary label => value */
    private function summary(array $summary): array
    {
        return collect($summary)->mapWithKeys(fn ($value, $label) => [__($label) => $value])->all();
    }
}
