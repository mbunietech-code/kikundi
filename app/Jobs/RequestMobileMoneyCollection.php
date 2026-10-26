<?php

namespace App\Jobs;

use App\Integrations\AzamPay\AzamPayClient;
use App\Integrations\AzamPay\MobileNetwork;
use App\Models\PaymentIntent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one AzamPay push for a pending payment request. Never retried, so a
 * member is not prompted twice; on failure the request stays pending for the
 * treasurer to verify by hand.
 */
class RequestMobileMoneyCollection implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $intentId) {}

    public function handle(AzamPayClient $azampay): void
    {
        $intent = PaymentIntent::query()->find($this->intentId);

        if (! $intent || $intent->status !== 'pending' || $intent->expires_at?->isPast()) {
            return;
        }

        try {
            $result = $azampay->mnoCheckout($intent->payer_msisdn, (int) $intent->amount, $intent->reference, MobileNetwork::provider($intent->payer_msisdn));
        } catch (Throwable $e) {
            Log::warning('AzamPay push failed; left for manual verification', ['reference' => $intent->reference, 'error' => $e->getMessage()]);

            return;
        }

        Log::info('AzamPay push sent', ['reference' => $intent->reference, 'success' => $result['success'], 'transaction' => $result['transactionId'], 'message' => $result['message']]);
    }
}
