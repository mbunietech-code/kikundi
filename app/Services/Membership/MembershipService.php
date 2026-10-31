<?php

namespace App\Services\Membership;

use App\Jobs\SendSmsNotification;
use App\Models\Allocation;
use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\MembershipFee;
use App\Models\PaymentIntent;
use App\Models\SentReminder;
use App\Models\UssdSession;
use App\Services\Balances\BalanceService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Membership rules from the group constitution: entry fee, losing active
 * status after unpaid months, and coming back after a reinstatement fee.
 * Every status change is kept in member_status_changes.
 */
class MembershipService
{
    public function __construct(private BalanceService $balances) {}

    /**
     * Set the starting status of a newly registered member. Existing members
     * being moved into the system can have the entry fee waived.
     */
    public function register(Member $member, bool $waiveEntryFee, ?int $userId = null): void
    {
        $fee = (int) Settings::get('entry_fee');

        if ($waiveEntryFee || $fee <= 0) {
            $this->changeStatus($member, 'active', $waiveEntryFee ? 'Amesajiliwa bila kiingilio (mwanachama wa zamani)' : 'Amesajiliwa', $userId);

            return;
        }

        $this->createFee($member, 'entry', $fee);
        $this->changeStatus($member, 'pending', 'Amesajiliwa; anasubiri kiingilio cha :amount', $userId, ['amount' => Money::format($fee)]);
    }

    /**
     * The reason is a translation key (or an officer's own words) and its
     * values, so the history reads in the viewer's language.
     */
    public function changeStatus(Member $member, string $to, string $reason, ?int $userId = null, array $params = []): void
    {
        $from = $member->getOriginal('status') ?? $member->status;

        if ($member->exists && $from === $to && $member->statusChanges()->exists()) {
            return;
        }

        $member->forceFill(['status' => $to, 'status_changed_at' => now()])->save();

        $member->statusChanges()->create([
            'group_id' => $member->group_id,
            'from_status' => $member->wasRecentlyCreated ? null : $from,
            'to_status' => $to,
            'reason' => $reason,
            'reason_params' => $params ?: null,
            'changed_by' => $userId,
        ]);
    }

    /** Past months with dues still owed. The current month is not yet late. */
    public function unpaidMonths(Member $member): int
    {
        return $this->balances->unpaidDues($member->group_id)
            ->where('member_id', $member->id)
            ->where('period', '<', now()->format('Y-m'))
            ->count();
    }

    /**
     * Mark active members who owe more months than the constitution allows
     * as not active. Safe to run daily.
     *
     * @return int number of members marked not active
     */
    public function enforce(int $groupId): int
    {
        $grace = max(1, (int) Settings::get('grace_months'));

        $late = $this->balances->unpaidDues($groupId)
            ->where('period', '<', now()->format('Y-m'))
            ->countBy('member_id')
            ->filter(fn (int $months) => $months >= $grace);

        $members = Member::query()->where('group_id', $groupId)->where('status', 'active')->whereIn('id', $late->keys())->get();

        foreach ($members as $member) {
            $months = $late[$member->id];
            $this->changeStatus($member, 'inactive', 'Hajalipa ada kwa miezi :months', null, ['months' => $months]);

            SendSmsNotification::dispatch($member->phone, __(
                'Ndugu :name, kwa kutolipa ada miezi :months, wewe si mwanachama hai wa :system. Wasiliana na mweka hazina ili kurejea.',
                ['name' => $member->full_name, 'months' => $months, 'system' => Settings::systemName()],
                $member->preferredLocale(),
            ));
        }

        return $members->count();
    }

    /** Ask a member who is not active to pay the reinstatement fee. */
    public function startReinstatement(Member $member, ?int $userId = null): MembershipFee
    {
        // A manual suspension is lifted by hand, not by paying.
        if ($member->status !== 'inactive') {
            throw new RuntimeException(__('Mwanachama huyu si wa kurejeshwa.'));
        }

        $open = $member->fees()->where('kind', 'reinstatement')->get()->first(fn ($fee) => $this->balances->feeOutstanding($fee) > 0);

        if ($open) {
            return $open;
        }

        $fee = $this->createFee($member, 'reinstatement', (int) Settings::get('reinstatement_fee'));

        $member->statusChanges()->create([
            'group_id' => $member->group_id,
            'from_status' => $member->status,
            'to_status' => $member->status,
            'reason' => 'Ameanza kurejea; kiingilio cha :amount',
            'reason_params' => ['amount' => Money::format($fee->amount_due)],
            'changed_by' => $userId,
        ]);

        $this->review($member->fresh());

        return $fee;
    }

    /**
     * What is still missing before the member can be active again.
     *
     * @return list<string>
     */
    public function missingConditions(Member $member): array
    {
        $missing = [];
        $kind = $member->status === 'pending' ? 'entry' : 'reinstatement';
        $fee = $member->fees()->where('kind', $kind)->latest('id')->first();

        if ($kind === 'reinstatement' && ! $fee) {
            return [__('Kurejea hakujaanzishwa')];
        }

        if ($fee && ($owed = $this->balances->feeOutstanding($fee)) > 0) {
            $missing[] = __($fee->label()).' '.Money::format($owed);
        }

        if ($kind === 'reinstatement' && Settings::get('reinstate_requires_arrears') === '1') {
            $arrears = $this->balances->unpaidDues($member->group_id)->where('member_id', $member->id)->sum('outstanding');

            if ($arrears > 0) {
                $missing[] = __('Madeni ya ada :amount', ['amount' => Money::format($arrears)]);
            }
        }

        return $missing;
    }

    /**
     * Activate a pending or not-active member once every condition is met.
     * Called after each verified payment.
     */
    public function review(Member $member): bool
    {
        if (! in_array($member->status, ['pending', 'inactive'], true) || $this->missingConditions($member)) {
            return false;
        }

        $returning = $member->status === 'inactive';
        $this->changeStatus($member, 'active', $returning ? 'Amerejeshwa baada ya kutimiza masharti' : 'Kiingilio kimelipwa');

        SendSmsNotification::dispatch($member->phone, __(
            $returning ? 'Karibu tena! Sasa wewe ni mwanachama hai wa :system.' : 'Karibu! Sasa wewe ni mwanachama hai wa :system.',
            ['system' => Settings::systemName()],
            $member->preferredLocale(),
        ));

        return true;
    }

    /**
     * A member can be deleted only while no money has been recorded for or
     * by them; otherwise reports and balances would change. Such members are
     * marked as departed instead.
     */
    public function canDelete(Member $member): bool
    {
        return $this->withPaymentHistory([$member->id]) === [];
    }

    /**
     * Which of these members have money recorded for or by them, in two
     * queries, so a whole list page can show its delete buttons.
     *
     * @param  list<int>  $memberIds
     * @return list<int>
     */
    public function withPaymentHistory(array $memberIds): array
    {
        $credited = Allocation::query()->whereIn('credited_member_id', $memberIds)->distinct()->pluck('credited_member_id');
        $intents = PaymentIntent::query()->where('status', 'succeeded')
            ->where(fn ($q) => $q->whereIn('payer_member_id', $memberIds)->orWhereIn('credited_member_id', $memberIds))
            ->get(['payer_member_id', 'credited_member_id']);

        return $credited->merge($intents->pluck('payer_member_id'))->merge($intents->pluck('credited_member_id'))
            ->map(fn ($id) => (int) $id)->intersect($memberIds)->unique()->values()->all();
    }

    /** Remove a member registered by mistake, with everything that only belongs to them. */
    public function delete(Member $member): void
    {
        if (! $this->canDelete($member)) {
            throw new RuntimeException(__('Mwanachama huyu ana malipo yaliyorekodiwa, kwa hiyo hawezi kufutwa. Badilisha hali yake kuwa "Ameondoka".'));
        }

        DB::transaction(function () use ($member) {
            PaymentIntent::query()->where(fn ($q) => $q->where('payer_member_id', $member->id)->orWhere('credited_member_id', $member->id))->delete();
            ContributionEvent::query()->where('beneficiary_member_id', $member->id)->update(['beneficiary_member_id' => null]);
            UssdSession::query()->where('member_id', $member->id)->update(['member_id' => null]);
            SentReminder::query()->where('member_id', $member->id)->delete();
            $member->obligations()->delete();
            $member->fees()->delete();
            $member->statusChanges()->delete();
            $member->dependants()->delete();
            $member->guarantors()->delete();
            // Records where this member vouched for others keep the name and phone.
            $member->guaranteed()->update(['guarantor_member_id' => null]);
            $member->delete();
        });

        if ($member->photo_path) {
            Storage::disk('local')->delete($member->photo_path);
        }
    }

    private function createFee(Member $member, string $kind, int $amount): MembershipFee
    {
        return $member->fees()->create([
            'group_id' => $member->group_id,
            'kind' => $kind,
            'amount_due' => $amount,
            'currency' => $member->group->currency,
        ]);
    }
}
