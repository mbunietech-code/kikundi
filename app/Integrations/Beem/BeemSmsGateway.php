<?php

namespace App\Integrations\Beem;

use App\Contracts\SmsGateway;
use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Beem SMS. Keys set by the administrator in the system settings win over
 * the ones in .env.
 */
class BeemSmsGateway implements SmsGateway
{
    private const BALANCE_URL = 'https://apisms.beem.africa/public/v1/vendors/balance';

    private const DELIVERY_URL = 'https://dlrapi.beem.africa/public/v1/delivery-reports';

    /** @return string|null Beem's request id, needed to look up delivery later */
    public function send(string $msisdn, string $message): ?string
    {
        $response = $this->request()->post(config('beem.sms.url'), [
            'source_addr' => self::senderId(),
            'encoding' => 0,
            'message' => $message,
            'recipients' => [['recipient_id' => 1, 'dest_addr' => $msisdn]],
        ]);

        // Beem can answer 200 and still refuse the number, so read the body too.
        $context = ['to' => $msisdn, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)];

        if (! $response->successful() || $response->json('successful') === false || (int) $response->json('valid', 1) < 1) {
            Log::warning('Beem SMS refused', $context);

            throw new RuntimeException(__('Beem imekataa (:status): :body', ['status' => $response->status(), 'body' => mb_substr((string) ($response->json('message') ?? $response->body()), 0, 200)]));
        }

        $requestId = $response->json('request_id') !== null ? (string) $response->json('request_id') : null;
        Log::info('Beem SMS accepted', ['to' => $msisdn, 'request_id' => $requestId]);

        return $requestId;
    }

    /** Beem's delivery status for a sent message, e.g. DELIVERED, PENDING or UNDELIVERED. */
    public function deliveryStatus(string $msisdn, string $requestId): ?string
    {
        $response = $this->request()->get(self::DELIVERY_URL, ['dest_addr' => $msisdn, 'request_id' => $requestId]);

        if (! $response->successful()) {
            throw new RuntimeException(__('Beem imekataa (:status): :body', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 200)]));
        }

        $rows = $response->json();
        $row = is_array($rows) && array_is_list($rows) ? ($rows[0] ?? null) : $rows;

        return is_array($row) ? ($row['status'] ?? null) : null;
    }

    /** SMS credit left on the Beem account. */
    public function balance(): float
    {
        $response = $this->request()->get(self::BALANCE_URL);

        if (! $response->successful()) {
            throw new RuntimeException(__('Beem imekataa (:status): :body', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 200)]));
        }

        return (float) $response->json('data.credit_balance');
    }

    public static function configured(): bool
    {
        return filled(self::apiKey()) && filled(self::secretKey());
    }

    public static function apiKey(): ?string
    {
        return Settings::get('beem_api_key') ?: config('beem.api_key');
    }

    public static function secretKey(): ?string
    {
        return Settings::get('beem_secret_key') ?: config('beem.secret_key');
    }

    public static function senderId(): string
    {
        return Settings::get('beem_sender_id') ?: config('beem.sms.sender_id');
    }

    private function request(): PendingRequest
    {
        if (! self::configured()) {
            throw new RuntimeException(__('Funguo za Beem hazijawekwa.'));
        }

        return Http::withBasicAuth(self::apiKey(), self::secretKey())->acceptJson()->timeout(15);
    }
}
