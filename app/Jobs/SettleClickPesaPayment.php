<?php

namespace App\Jobs;

use App\Integrations\ClickPesa\ClickPesaSettlement;
use App\Models\PaymentIntent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Checks a ClickPesa order and posts it if paid. Safe to run more than once. */
class SettleClickPesaPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $intentId) {}

    public function handle(ClickPesaSettlement $settlement): void
    {
        $intent = PaymentIntent::query()->find($this->intentId);

        if (! $intent) {
            return;
        }

        try {
            $settlement->settle($intent);
        } catch (Throwable $e) {
            Log::warning('ClickPesa status check failed', ['reference' => $intent->reference, 'error' => $e->getMessage()]);
        }
    }
}
