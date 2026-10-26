<?php

namespace App\Integrations\Beem;

use App\Contracts\PaymentGateway;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Log;

/**
 * Used until Beem Payments is approved: the intent stays pending and the
 * treasurer verifies the receipt against official records.
 */
class ManualPaymentGateway implements PaymentGateway
{
    public function requestCollection(PaymentIntent $intent): void
    {
        Log::info('Payment intent awaiting manual verification', ['reference' => $intent->reference]);
    }
}
