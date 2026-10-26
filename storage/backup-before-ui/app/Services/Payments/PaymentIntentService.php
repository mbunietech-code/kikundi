<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Member;
use App\Models\PaymentIntent;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentIntentService
{
    public const TARGET_TYPES = ['dues', 'savings', 'event'];

    public function __construct(private PaymentGateway $gateway) {}

    /**
     * Create a pending request. A repeated call with the same idempotency key
     * returns the original intent instead of creating a second one.
     */
    public function create(Member $payer, Member $credited, string $targetType, ?int $targetId, int $amount, string $idempotencyKey, string $channel = 'ussd', ?string $payerMsisdn = null): PaymentIntent
    {
        if (! in_array($targetType, self::TARGET_TYPES, true)) {
            throw new InvalidArgumentException("Unknown target type [{$targetType}].");
        }

        if ($payer->group_id !== $credited->group_id) {
            throw new InvalidArgumentException('Payer and credited member must belong to the same group.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        $existing = PaymentIntent::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $intent = PaymentIntent::query()->create([
            'group_id' => $payer->group_id,
            'payer_member_id' => $payer->id,
            'credited_member_id' => $credited->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'amount' => $amount,
            'currency' => $payer->group->currency,
            'reference' => $this->newReference(),
            'idempotency_key' => $idempotencyKey,
            'channel' => $channel,
            'payer_msisdn' => $payerMsisdn,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $this->gateway->requestCollection($intent);

        return $intent;
    }

    private function newReference(): string
    {
        do {
            $reference = 'MJ'.strtoupper(Str::random(8));
        } while (PaymentIntent::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
