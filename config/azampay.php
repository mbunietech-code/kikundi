<?php

return [
    // "sandbox" or "production". Hosts differ per environment; mixing them is
    // the most common setup failure.
    'environment' => env('AZAMPAY_ENVIRONMENT', 'sandbox'),

    'hosts' => [
        'sandbox' => [
            'auth' => 'https://authenticator-sandbox.azampay.co.tz',
            'checkout' => 'https://sandbox.azampay.co.tz',
        ],
        'production' => [
            'auth' => 'https://authenticator.azampay.co.tz',
            'checkout' => 'https://checkout.azampay.co.tz',
        ],
    ],

    // From the AzamPay developer portal (sandbox: developers.azampay.co.tz).
    'app_name' => env('AZAMPAY_APP_NAME'),
    'client_id' => env('AZAMPAY_CLIENT_ID'),
    'client_secret' => env('AZAMPAY_CLIENT_SECRET'),
    'api_key' => env('AZAMPAY_API_KEY'),

    // Secret path segment of the callback URL registered with AzamPay, and the
    // optional callback password AzamPay sends in the callback body.
    'callback_token' => env('AZAMPAY_CALLBACK_TOKEN'),
    'callback_password' => env('AZAMPAY_CALLBACK_PASSWORD'),

    // Seconds to wait before the push, so the member's USSD session has closed.
    'push_delay' => (int) env('AZAMPAY_PUSH_DELAY', 5),
];
