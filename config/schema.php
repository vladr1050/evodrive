<?php

return [
    'organization' => [
        'name' => env('SCHEMA_ORG_NAME', 'EvoDrive'),
        'url' => env('APP_URL', 'https://evodrive.lv'),
    ],
    'job_location' => [
        'address_locality' => env('SCHEMA_JOB_LOCALITY', 'Riga'),
        'address_country' => env('SCHEMA_JOB_COUNTRY', 'LV'),
    ],
    'job_posting' => [
        'date_posted' => env('SCHEMA_JOB_DATE_POSTED', null), // Y-m-d, null = today
        'base_salary' => [
            'currency' => 'EUR',
            'value' => [
                'min' => 12,
                'max' => 15,
                'unit' => 'HOUR',
            ],
        ],
    ],
];
