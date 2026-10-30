<?php

namespace App\Services\Payments;

use App\Models\Allocation;
use App\Models\DeletionLog;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Services\Reports\ReportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deletes payments entered by mistake, alone or with the member who made
 * them. Every deletion is written to deletion_logs with who, why and what,
 * because the payment rows themselves are gone afterwards.
 */
class PaymentRemovalService
{
    public function __construct(private MembershipService $membership) {}

    public function deletePayment(PaymentIntent $intent, string $reason, User $user): DeletionLog
    {
        return DB::transaction(function () use ($intent, $reason, $user) {
            $row = $this->describe($intent);
            $this->remove($intent);

            return DeletionLog::query()->create([
                'group_id' => $intent->group_id,
                'user_id' => $user->id,
                'kind' => 'payment',
                'subject' => $intent->reference.' · '.$row['member'],
                'amount' => $row['status'] === 'succeeded' ? $row['amount'] : 0,
                'details' => ['payments' => [$row]],
                'reason' => $reason,
            ]);
        });
    }

    /**
     * What deleting this member would take with it, for the confirmation page.
     *
     * @return array{intents: Collection<int, PaymentIntent>, count: int, total: int, by_type: array<string, int>, for_others: Collection<int, PaymentIntent>}
     */
    public function memberSummary(Member $member): array
    {
        $intents = $this->intentsOf($member)->load(['payer', 'creditedMember']);
        $paid = $intents->where('status', 'succeeded');

        return [
            'intents' => $intents,
            'count' => $paid->count(),
            'total' => (int) $paid->sum('amount'),
            'by_type' => $paid->groupBy('target_type')->map(fn ($rows) => (int) $rows->sum('amount'))->all(),
            // Paid by this member for someone else: deleting changes the other member's balance.
            'for_others' => $paid->filter(fn ($i) => $i->payer_member_id === $member->id && $i->credited_member_id !== $member->id)->values(),
        ];
    }

    public function deleteMemberWithPayments(Member $member, string $reason, User $user): DeletionLog
    {
        return DB::transaction(function () use ($member, $reason, $user) {
            $intents = $this->intentsOf($member)->load(['payer', 'creditedMember']);
            $rows = $intents->map(fn ($intent) => $this->describe($intent))->values()->all();

            $intents->each(fn ($intent) => $this->remove($intent));
            $this->membership->delete($member->fresh());

            return DeletionLog::query()->create([
                'group_id' => $member->group_id,
                'user_id' => $user->id,
                'kind' => 'member',
                'subject' => $member->member_number.' · '.$member->full_name,
                'amount' => (int) collect($rows)->where('status', 'succeeded')->sum('amount'),
                'details' => ['phone' => $member->phone, 'payments' => $rows],
                'reason' => $reason,
            ]);
        });
    }

    /** @return Collection<int, PaymentIntent> */
    private function intentsOf(Member $member): Collection
    {
        return PaymentIntent::query()
            ->where(fn ($q) => $q->where('payer_member_id', $member->id)->orWhere('credited_member_id', $member->id))
            ->orderBy('id')
            ->get();
    }

    private function remove(PaymentIntent $intent): void
    {
        $paymentIds = Payment::query()->where('payment_intent_id', $intent->id)->pluck('id');
        $allocationIds = Allocation::query()->whereIn('payment_id', $paymentIds)->pluck('id');

        Allocation::query()->whereIn('reversal_of_id', $allocationIds)->update(['reversal_of_id' => null]);
        Allocation::query()->whereIn('id', $allocationIds)->delete();
        Payment::query()->whereIn('id', $paymentIds)->delete();
        $intent->delete();
    }

    /** A plain copy of the payment for the log, readable after the rows are gone. */
    private function describe(PaymentIntent $intent): array
    {
        $payment = Payment::query()->where('payment_intent_id', $intent->id)->first();

        return [
            'reference' => $intent->reference,
            'member' => $intent->creditedMember?->full_name.' ('.$intent->creditedMember?->member_number.')',
            'payer' => $intent->payer_member_id !== $intent->credited_member_id ? $intent->payer?->full_name : null,
            'type' => ReportService::TARGETS[$intent->target_type] ?? $intent->target_type,
            'amount' => (int) $intent->amount,
            'status' => $intent->status,
            'method' => $payment?->provider,
            'transaction' => $payment?->provider_transaction_id,
            'date' => ($payment?->verified_at ?? $intent->created_at)?->format('d/m/Y H:i'),
        ];
    }
}
