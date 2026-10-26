<?php

namespace App\Services\Reminders;

use App\Jobs\SendSmsNotification;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\SentReminder;
use App\Services\Balances\BalanceService;
use App\Services\Dues\DuesService;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * SMS reminders before payment deadlines, overdue notices after them, and
 * debt statements sent by an officer. Automatic messages are logged in
 * sent_reminders so running the job twice never sends twice.
 */
class ReminderService
{
    public function __construct(private BalanceService $balances, private DuesService $dues) {}

    /** The day this month's dues must be paid by. */
    public function duesDeadline(?CarbonImmutable $month = null): CarbonImmutable
    {
        $month = ($month ?? CarbonImmutable::now())->startOfMonth();
        $day = min(max(1, (int) Settings::get('dues_due_day')), $month->daysInMonth);

        return $month->setDay($day)->endOfDay();
    }

    /**
     * Everything due today: make sure this month's dues exist, then send
     * the reminders and overdue notices whose time has come.
     *
     * @return array{dues_reminders: int, dues_overdue: int, event_reminders: int}
     */
    public function runDaily(int $groupId, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();
        $this->dues->generate($groupId, $today->format('Y-m'));

        if (Settings::get('reminders_enabled') !== '1') {
            return ['dues_reminders' => 0, 'dues_overdue' => 0, 'event_reminders' => 0];
        }

        return [
            'dues_reminders' => $this->duesReminders($groupId, $today),
            'dues_overdue' => $this->duesOverdue($groupId, $today),
            'event_reminders' => $this->eventReminders($groupId, $today),
        ];
    }

    /**
     * Send each debtor a statement of what they owe. Limited to once a day
     * per member so a double click does not send twice.
     */
    public function sendDebtNotices(int $groupId): int
    {
        $sent = 0;
        $members = Member::query()->where('group_id', $groupId)->where('status', '!=', 'departed')->get()->keyBy('id');

        foreach ($this->balances->debtBreakdowns($groupId) as $memberId => $parts) {
            $member = $members[$memberId] ?? null;

            if (! $member) {
                continue;
            }

            $locale = $member->preferredLocale();
            $lines = array_filter([
                $parts['dues'] ? __('Ada', [], $locale).' '.Money::format($parts['dues']) : null,
                $parts['fees'] ? __('Kiingilio', [], $locale).' '.Money::format($parts['fees']) : null,
                $parts['events'] ? __('Michango', [], $locale).' '.Money::format($parts['events']) : null,
            ]);

            $sent += $this->send($member, 'debt-notice:'.now()->toDateString(),
                'Ndugu :name, deni lako :system: :lines. Jumla :total. Tafadhali lipa mapema.',
                ['name' => $member->full_name, 'system' => Settings::systemName(), 'lines' => implode(', ', $lines), 'total' => Money::format(array_sum($parts))]);
        }

        return $sent;
    }

    /** From N days before the deadline up to the deadline itself. */
    private function duesReminders(int $groupId, CarbonImmutable $today): int
    {
        $deadline = $this->duesDeadline($today);
        $from = $deadline->startOfDay()->subDays((int) Settings::get('reminder_days_before'));

        if ($today < $from || $today > $deadline) {
            return 0;
        }

        $period = $today->format('Y-m');

        return $this->currentDuesOwed($groupId, $period)->sum(fn (array $row) => $this->send($row['member'], "dues-reminder:{$period}",
            'Ukumbusho: ada ya :month :amount inatakiwa kulipwa kabla ya :date. :system',
            ['month' => $today, 'amount' => Money::format($row['owed']), 'date' => $deadline->format('d/m/Y'), 'system' => Settings::systemName()]));
    }

    /** Once, any day after the deadline, to those still owing this month. */
    private function duesOverdue(int $groupId, CarbonImmutable $today): int
    {
        $deadline = $this->duesDeadline($today);

        if ($today <= $deadline) {
            return 0;
        }

        $period = $today->format('Y-m');
        $totals = $this->balances->debtsByMember($groupId);

        return $this->currentDuesOwed($groupId, $period)->sum(fn (array $row) => $this->send($row['member'], "dues-overdue:{$period}",
            'Ada ya :month haijalipwa na muda umepita (:date). Deni lako lote ni :total. :system',
            ['month' => $today, 'date' => $deadline->format('d/m/Y'), 'total' => Money::format($totals[$row['member']->id] ?? $row['owed']), 'system' => Settings::systemName()]));
    }

    private function eventReminders(int $groupId, CarbonImmutable $today): int
    {
        $days = (int) Settings::get('reminder_days_before');

        $events = ContributionEvent::query()
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->where('amount_per_member', '>', 0)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$today, $today->addDays($days)->endOfDay()])
            ->get();

        $sent = 0;

        foreach ($events as $event) {
            $owing = $this->balances->eventDebts($groupId, $event->id);
            $members = Member::query()->whereIn('id', $owing->pluck('member_id'))->get()->keyBy('id');

            foreach ($owing as $row) {
                $sent += $this->send($members[$row->member_id], "event-reminder:{$event->id}",
                    'Ukumbusho: mchango wa :title :amount kabla ya :date. :system',
                    ['title' => $event->title, 'amount' => Money::format($row->outstanding), 'date' => $event->ends_at->format('d/m/Y'), 'system' => Settings::systemName()]);
            }
        }

        return $sent;
    }

    /** @return Collection<int, array{member: Member, owed: int}> */
    private function currentDuesOwed(int $groupId, string $period): Collection
    {
        $owed = $this->balances->unpaidDues($groupId)->where('period', $period)->keyBy('member_id');

        return Member::query()->where('group_id', $groupId)->where('status', 'active')->whereIn('id', $owed->keys())->get()
            ->map(fn (Member $m) => ['member' => $m, 'owed' => $owed[$m->id]->outstanding]);
    }

    /**
     * Send in the member's own language. A date among the values becomes the
     * month name in that language.
     *
     * @return int 1 if sent now, 0 if this reminder was already sent
     */
    private function send(Member $member, string $key, string $message, array $params): int
    {
        try {
            SentReminder::query()->create(['group_id' => $member->group_id, 'member_id' => $member->id, 'key' => $key]);
        } catch (UniqueConstraintViolationException) {
            return 0;
        }

        $locale = $member->preferredLocale();
        $params = array_map(fn ($value) => $value instanceof CarbonImmutable ? $value->locale($locale)->translatedFormat('F') : $value, $params);

        SendSmsNotification::dispatch($member->phone, __($message, $params, $locale));

        return 1;
    }
}
