<?php

namespace App\Integrations\ClickPesa;

use App\Contracts\PaymentGateway;
use App\Integrations\AzamPay\MobileNetwork;
use App\Jobs\RequestClickPesaCollection;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Log;

/**
 * Collects USSD payment requests through ClickPesa: the member gets their
 * network's payment prompt, and the payment is posted once ClickPesa
 * confirms it. Anything else stays pending for the treasurer.
 */
class ClickPesaGateway implements PaymentGateway
{
    public function requestCollection(PaymentIntent $intent): void
    {
        if (! $this->promptsOnPhone($intent)) {
            Log::info('Payment intent awaiting manual verification', ['reference' => $intent->reference, 'channel' => $intent->channel]);

            return;
        }

        // Delayed so the member's USSD session has closed before the prompt.
        RequestClickPesaCollection::dispatch($intent->id)
            ->delay(now()->addSeconds(config('azampay.push_delay')))
            ->afterCommit();
    }

    public function promptsOnPhone(PaymentIntent $intent): bool
    {
        return $intent->channel === 'ussd' && ClickPesaClient::configured() && MobileNetwork::provider($intent->payer_msisdn) !== null;
    }
}
