<?php

namespace App\Integrations\AzamPay;

use App\Contracts\PaymentGateway;
use App\Jobs\RequestMobileMoneyCollection;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Log;

/**
 * Collects USSD payment requests through AzamPay: the member gets their
 * network's payment prompt and the payment is posted when AzamPay calls back.
 * Anything AzamPay cannot handle stays pending for the treasurer, as before.
 */
class AzamPayGateway implements PaymentGateway
{
    public function requestCollection(PaymentIntent $intent): void
    {
        if (! $this->promptsOnPhone($intent)) {
            Log::info('Payment intent awaiting manual verification', ['reference' => $intent->reference, 'channel' => $intent->channel]);

            return;
        }

        // Queued with a short delay: the prompt cannot show while the member's
        // USSD session is still open, and the USSD reply must not wait on AzamPay.
        RequestMobileMoneyCollection::dispatch($intent->id)
            ->delay(now()->addSeconds(config('azampay.push_delay')))
            ->afterCommit();
    }

    /** Only USSD requests from a supported network; office payments are already verified. */
    public function promptsOnPhone(PaymentIntent $intent): bool
    {
        return $intent->channel === 'ussd' && MobileNetwork::provider($intent->payer_msisdn) !== null;
    }
}
