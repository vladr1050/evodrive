<?php

return [
    'ness' => [
        'api_key' => env('NESS_SMS_API_KEY', ''),
        'sender_id' => env('NESS_SMS_SENDER_ID', 'EvoDrive'),
        'base_url' => env('NESS_SMS_BASE_URL', 'https://traffic.sales.lv/API:0.16/'),
    ],
];
