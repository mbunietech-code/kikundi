<?php

namespace App\Integrations\ClickPesa;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for ClickPesa's USSD-push collection API. One integration
 * covers M-Pesa, Mixx by Yas, Airtel Money and HaloPesa.
 */
class ClickPesaClient
{
    private const BASE = 'https://api.clickpesa.com/third-parties';

    private const TOKEN_CACHE_KEY = 'clickpesa.token';

    public static function configured(): bool
    {
        return filled(Settings::get('clickpesa_client_id')) && filled(Settings::get('clickpesa_api_key'));
    }

    public function token(): string
    {
        // Tokens last an hour; keep one a little less.
        return Cache::remember(self::TOKEN_CACHE_KEY.'.'.md5((string) Settings::get('clickpesa_client_id')), now()->addMinutes(50), function () {
            $response = Http::acceptJson()->timeout(20)
                ->withHeaders(['client-id' => Settings::get('clickpesa_client_id'), 'api-key' => Settings::get('clickpesa_api_key')])
                ->post(self::BASE.'/generate-token');

            $token = (string) $response->json('token');

            if (! $response->successful() || $token === '') {
                throw new RuntimeException('ClickPesa token request failed ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
            }

            // ClickPesa returns the value with its "Bearer " prefix.
            return str_starts_with($token, 'Bearer ') ? substr($token, 7) : $token;
        });
    }

    /** Ask the member's network to show a payment prompt on their phone. */
    public function ussdPush(string $msisdn, int $amount, string $orderReference): array
    {
        $payload = ['amount' => (string) $amount, 'currency' => 'TZS', 'orderReference' => $orderReference, 'phoneNumber' => $msisdn];

        return $this->send('post', '/payments/initiate-ussd-push-request', $this->withChecksum($payload));
    }

    /**
     * Payment records for an order reference, straight from ClickPesa.
     *
     * @return list<array<string, mixed>>
     */
    public function payments(string $orderReference): array
    {
        $result = $this->send('get', '/payments/'.rawurlencode($orderReference));

        return array_is_list($result) ? $result : [$result];
    }

    /** @return list<array{currency: string, balance: float}> */
    public function balance(): array
    {
        $result = $this->send('get', '/account/balance');

        return array_map(fn ($row) => ['currency' => (string) ($row['currency'] ?? 'TZS'), 'balance' => (float) ($row['balance'] ?? 0)], array_is_list($result) ? $result : [$result]);
    }

    private function send(string $method, string $path, array $payload = []): array
    {
        $response = $this->request()->{$method}(self::BASE.$path, $payload);

        // An expired token is the usual 401; drop it so the next try gets a new one.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY.'.'.md5((string) Settings::get('clickpesa_client_id')));
        }

        if (! $response->successful()) {
            throw new RuntimeException('ClickPesa '.$path.' failed ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
        }

        return (array) $response->json();
    }

    /** Only needed when checksums are switched on in the ClickPesa dashboard. */
    private function withChecksum(array $payload): array
    {
        $key = Settings::get('clickpesa_checksum_key');

        if (blank($key)) {
            return $payload;
        }

        ksort($payload);

        return $payload + ['checksum' => hash_hmac('sha256', implode('', $payload), $key)];
    }

    private function request(): PendingRequest
    {
        if (! self::configured()) {
            throw new RuntimeException(__('Funguo za ClickPesa hazijawekwa.'));
        }

        return Http::acceptJson()->timeout(30)->withToken($this->token());
    }
}
