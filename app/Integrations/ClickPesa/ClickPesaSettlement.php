<?php

namespace App\Integrations\ClickPesa;

use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Services\Payments\VerifiedPaymentService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Posts a ClickPesa payment only after asking ClickPesa itself for the
 * order's status, so a forged webhook cannot create money.
 */
class ClickPesaSettlement
{
    private const PAID = ['SUCCESS', 'SETTLED'];

    private const FAILED = ['FAILED', 'REJECTED', 'CANCELLED', 'REVERSED'];

    /** network name in ClickPesa results => payment method */
    private const CHANNELS = ['mpesa' => 'mpesa', 'vodacom' => 'mpesa', 'tigo' => 'tigopesa', 'mixx' => 'tigopesa', 'yas' => 'tigopesa', 'airtel' => 'airtelmoney', 'halo' => 'halopesa'];

    public function __construct(private ClickPesaClient $clickpesa, private VerifiedPaymentService $payments) {}

    public function settle(PaymentIntent $intent): ?Payment
    {
        if ($intent->status !== 'pending') {
            return null;
        }

        $records = $this->clickpesa->payments($intent->reference);
        $paid = collect($records)->first(fn ($r) => in_array(strtoupper((string) ($r['status'] ?? '')), self::PAID, true));

        if (! $paid) {
            if (collect($records)->isNotEmpty() && collect($records)->every(fn ($r) => in_array(strtoupper((string) ($r['status'] ?? '')), self::FAILED, true))) {
                $intent->update(['status' => 'failed']);
            }

            return null;
        }

        $transaction = (string) ($paid['paymentReference'] ?? $paid['id'] ?? '');
        $amount = (int) round((float) ($paid['collectedAmount'] ?? $paid['amount'] ?? 0));

        if ($transaction === '') {
            Log::warning('ClickPesa paid order without a reference; left for manual verification', ['reference' => $intent->reference]);

            return null;
        }

        try {
            return $this->payments->post($intent, $this->method((string) ($paid['channel'] ?? '')), $transaction, $amount, 'provider');
        } catch (RuntimeException $e) {
            // Amount mismatch or already paid by another transaction: a person must look.
            Log::error('ClickPesa payment not posted', ['reference' => $intent->reference, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function method(string $channel): string
    {
        $channel = strtolower(preg_replace('/[^a-z]/i', '', $channel));

        foreach (self::CHANNELS as $needle => $method) {
            if (str_contains($channel, $needle)) {
                return $method;
            }
        }

        return 'clickpesa';
    }
}
