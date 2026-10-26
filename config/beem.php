<?php

return [
    // Secret path segment for the USSD callback URL registered with Beem.
    // Beem's USSD callback has no documented signature, so the URL itself is
    // kept secret and can optionally be restricted to Beem's IP addresses.
    'ussd_token' => env('BEEM_USSD_TOKEN'),
    'ussd_allowed_ips' => array_filter(explode(',', (string) env('BEEM_USSD_ALLOWED_IPS', ''))),

    // Group served by the USSD code during the single-group pilot.
    'ussd_group_id' => env('BEEM_USSD_GROUP_ID', 1),
    'ussd_pin_max_attempts' => 3,
    'ussd_pin_lock_minutes' => 30,

    'api_key' => env('BEEM_API_KEY'),
    'secret_key' => env('BEEM_SECRET_KEY'),

    'sms' => [
        // "beem" sends real SMS; "log" only writes them to the application log.
        'driver' => env('BEEM_SMS_DRIVER', 'log'),
        'url' => env('BEEM_SMS_URL', 'https://apisms.beem.africa/v1/send'),
        'sender_id' => env('BEEM_SMS_SENDER_ID', 'INFO'),
    ],
];
