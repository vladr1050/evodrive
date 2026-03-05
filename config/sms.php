<?php

return [
    'ness' => [
        'api_key' => env('NESS_SMS_API_KEY', ''),
        // Sender ID must be pre-approved by NESS for your account (alphanumeric 3–11 chars or numeric 3–14)
        'sender_id' => env('NESS_SMS_SENDER_ID', 'EvoDrive'),
        'base_url' => env('NESS_SMS_BASE_URL', 'https://traffic.sales.lv/API:0.16/'),
    ],
];
