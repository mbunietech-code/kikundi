<?php

namespace App\Services\Payments;

use App\Jobs\SendSmsNotification;
use App\Models\ContributionEvent;
use App\Models\MembershipFee;
use App\Models\Obligation;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Services\Balances\BalanceService;
use App\Services\Membership\MembershipService;
use App\Support\Locale;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posts a receipt only after the money has been verified, either from a
 * trusted provider result or by a treasurer against official records.
 */
class VerifiedPaymentService
{
    public function __construct(private MembershipService $membership, private BalanceService $balances) {}

    public function post(PaymentIntent $intent, string $provider, string $providerTransactionId, int $amount, string $method, ?int $verifiedBy = null): Payment
    {
        $payment = DB::transaction(function () use ($intent, $provider, $providerTransactionId, $amount, $method, $verifiedBy) {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);

            $existing = Payment::query()
                ->where('provider', $provider)
                ->where('provider_transaction_id', $providerTransactionId)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($intent->status === 'succeeded') {
                throw new RuntimeException("Intent {$intent->reference} is already paid by another transaction.");
            }

            if ($amount !== (int) $intent->amount) {
                throw new RuntimeException("Amount {$amount} does not match intent {$intent->reference} ({$intent->amount}).");
            }

            $payment = Payment::query()->create([
                'group_id' => $intent->group_id,
                'payment_intent_id' => $intent->id,
                'provider' => $provider,
                'provider_transaction_id' => $providerTransactionId,
                'amount' => $amount,
                'currency' => $intent->currency,
                'verification_method' => $method,
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
                'status' => 'posted',
            ]);
            $payment->update(['receipt_number' => Payment::receiptNumberFor($payment->id)]);

            $payment->allocations()->create([
                'group_id' => $intent->group_id,
                'target_type' => $intent->target_type,
                'target_id' => $intent->target_id,
                'credited_member_id' => $intent->credited_member_id,
                'amount' => $amount,
                'currency' => $intent->currency,
            ]);

            $intent->update(['status' => 'succeeded']);

            if ($intent->payer_msisdn || $intent->payer) {
                // The receipt says what was paid for and what the member still owes.
                $owed = (int) ($this->balances->debtsByMember($intent->group_id)[$intent->credited_member_id] ?? 0);
                $locale = $intent->payer?->preferredLocale() ?? Locale::default();
                $purpose = $this->purpose($intent, $locale)
                    .($intent->payer_member_id !== $intent->credited_member_id ? ' '.__('ya :name', ['name' => $intent->creditedMember->full_name], $locale) : '');

                SendSmsNotification::dispatch(
                    $intent->payer_msisdn ?: $intent->payer->phone,
                    __('Risiti :receipt: Tumepokea :amount kwa :purpose. Deni lililobaki: :owed. :system', [
                        'amount' => Money::format($amount, $intent->currency),
                        'purpose' => $purpose,
                        'receipt' => $payment->receipt_number,
                        'owed' => Money::format($owed, $intent->currency),
                        'system' => Settings::systemName(),
                    ], $locale),
                )->afterCommit();
            }

            return $payment;
        });

        // An entry fee or the last arrears may complete the membership conditions.
        $this->membership->review($intent->creditedMember()->first());

        return $payment;
    }

    public function purpose(PaymentIntent $intent, string $locale): string
    {
        return match ($intent->target_type) {
            'dues' => __('ada ya :month', ['month' => ($period = Obligation::query()->whereKey($intent->target_id)->value('period'))
                ? CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->locale($locale)->translatedFormat('F Y') : __('mwezi', [], $locale)], $locale),
            'event' => __('mchango wa :title', ['title' => ContributionEvent::query()->whereKey($intent->target_id)->value('title') ?? __('kikundi', [], $locale)], $locale),
            'membership_fee' => mb_strtolower(__(MembershipFee::query()->find($intent->target_id)?->label() ?? 'Kiingilio', [], $locale)),
            default => __('akiba', [], $locale),
        };
    }
}
