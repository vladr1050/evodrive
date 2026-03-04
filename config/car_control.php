<?php

return [
    /*
    | Access window: from start_at - window_minutes until end_at + window_minutes
    */
    'window_minutes' => (int) env('CAR_CONTROL_WINDOW_MINUTES', 45),

    /*
    | Rate limits (seconds)
    */
    'rate_limit_driver_seconds' => (int) env('CAR_CONTROL_RATE_LIMIT_DRIVER_SECONDS', 15),
    'rate_limit_vehicle_seconds' => (int) env('CAR_CONTROL_RATE_LIMIT_VEHICLE_SECONDS', 10),

    /*
    | Delay between two SMS when sending a pair (e.g. start_shift = UNLOCK_ENGINE + OPEN_CAR)
    */
    'pair_sms_delay_seconds' => (int) env('CAR_CONTROL_PAIR_SMS_DELAY_SECONDS', 3),

    /*
    | SMS command texts sent to vehicle phone (device-specific)
    */
    'commands' => [
        'open_car' => env('CAR_CONTROL_SMS_OPEN', 'youto youto lvcanopenalldoors'),
        'close_car' => env('CAR_CONTROL_SMS_CLOSE', 'youto youto lvcanclosealldoors'),
        'unlock_engine' => env('CAR_CONTROL_SMS_UNLOCK_ENGINE', 'youto youto setdigout 00 0 0'),
        'lock_engine' => env('CAR_CONTROL_SMS_LOCK_ENGINE', 'youto youto setdigout 10 0 0'),
    ],
];
