<?php

namespace App\Integrations\Beem;

use App\Contracts\SmsGateway;
use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Beem SMS. Keys set by the administrator in the system settings win over
 * the ones in .env.
 */
class BeemSmsGateway implements SmsGateway
{
    private const BALANCE_URL = 'https://apisms.beem.africa/public/v1/vendors/balance';

    public function send(string $msisdn, string $message): void
    {
        $this->request()->post(config('beem.sms.url'), [
            'source_addr' => self::senderId(),
            'encoding' => 0,
            'message' => $message,
            'recipients' => [['recipient_id' => 1, 'dest_addr' => $msisdn]],
        ])->throw();
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
