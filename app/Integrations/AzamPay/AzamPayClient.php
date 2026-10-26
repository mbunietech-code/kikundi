<?php

namespace App\Integrations\AzamPay;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for AzamPay's token and mobile-money checkout endpoints.
 */
class AzamPayClient
{
    private const TOKEN_CACHE_KEY = 'azampay.token';

    public function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY.'.'.config('azampay.environment'), now()->addMinutes(50), function () {
            $response = Http::acceptJson()->timeout(20)->post($this->host('auth').'/AppRegistration/GenerateToken', [
                'appName' => config('azampay.app_name'),
                'clientId' => config('azampay.client_id'),
                'clientSecret' => config('azampay.client_secret'),
            ]);

            $token = $response->json('data.accessToken');

            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new RuntimeException('AzamPay token request failed ('.$response->status().'): '.$response->body());
            }

            return $token;
        });
    }

    /**
     * Ask the member's network to show a payment prompt on their phone.
     *
     * @return array{success: bool, transactionId: ?string, message: ?string}
     */
    public function mnoCheckout(string $msisdn, int $amount, string $externalId, string $provider): array
    {
        $response = $this->request()->post($this->host('checkout').'/azampay/mno/checkout', [
            'accountNumber' => $msisdn,
            'amount' => (string) $amount,
            'currency' => 'TZS',
            'externalId' => $externalId,
            'provider' => $provider,
        ]);

        // An expired token is the usual 401; drop it so the next try gets a new one.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY.'.'.config('azampay.environment'));
        }

        if (! $response->successful()) {
            throw new RuntimeException('AzamPay checkout failed ('.$response->status().'): '.$response->body());
        }

        return [
            'success' => (bool) $response->json('success'),
            'transactionId' => $response->json('transactionId'),
            'message' => $response->json('message'),
        ];
    }

    public function configured(): bool
    {
        return filled(config('azampay.app_name')) && filled(config('azampay.client_id')) && filled(config('azampay.client_secret'));
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->withToken($this->token())
            ->when(filled(config('azampay.api_key')), fn (PendingRequest $http) => $http->withHeaders(['X-API-Key' => config('azampay.api_key')]));
    }

    private function host(string $kind): string
    {
        $environment = config('azampay.environment') === 'production' ? 'production' : 'sandbox';

        return config("azampay.hosts.{$environment}.{$kind}");
    }
}
