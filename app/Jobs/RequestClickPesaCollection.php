<?php

namespace App\Jobs;

use App\Integrations\ClickPesa\ClickPesaClient;
use App\Models\PaymentIntent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one ClickPesa push for a pending payment request. Never retried, so
 * a member is not prompted twice; on failure the request stays pending.
 */
class RequestClickPesaCollection implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $intentId) {}

    public function handle(ClickPesaClient $clickpesa): void
    {
        $intent = PaymentIntent::query()->find($this->intentId);

        if (! $intent || $intent->status !== 'pending' || $intent->expires_at?->isPast()) {
            return;
        }

        try {
            $result = $clickpesa->ussdPush($intent->payer_msisdn, (int) $intent->amount, $intent->reference);
        } catch (Throwable $e) {
            Log::warning('ClickPesa push failed; left for manual verification', ['reference' => $intent->reference, 'error' => $e->getMessage()]);

            return;
        }

        Log::info('ClickPesa push sent', ['reference' => $intent->reference, 'status' => $result['status'] ?? null, 'id' => $result['id'] ?? null]);

        // In case the webhook never arrives, ask ClickPesa again in a few minutes.
        SettleClickPesaPayment::dispatch($intent->id)->delay(now()->addMinutes(3));
    }
}
