<?php

namespace App\Services\Payments;

use App\Jobs\SendSmsNotification;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posts a receipt only after the money has been verified, either from a
 * trusted provider result or by a treasurer against official records.
 */
class VerifiedPaymentService
{
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
                SendSmsNotification::dispatch(
                    $intent->payer_msisdn ?: $intent->payer->phone,
                    'Tumepokea '.Money::format($amount, $intent->currency).'. Kumbukumbu '.$intent->reference.'. '.Settings::systemName(),
                )->afterCommit();
            }

            return $payment;
        });

        return $payment;
    }
}
