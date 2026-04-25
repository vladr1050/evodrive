<?php

return [
    /*
    | Global control channel: sms (legacy), gprs (Teltonika gateway), auto (GPRS then SMS on fallback).
    */
    'default_transport' => strtolower((string) env('CAR_CONTROL_TRANSPORT', 'sms')),

    /*
    | Teltonika gateway (HTTP API on internal network; TCP Codec12 is implemented in
    | ./teltonika-gateway — see teltonika-gateway/README.md).
    | Devices must use this host:port as FMC "Server 2" only; EvoDrive does not use Server 1.
    */
    'gprs' => [
        'internal_base_url' => rtrim((string) env('CAR_CONTROL_GPRS_INTERNAL_BASE_URL', ''), '/'),
        'internal_token' => env('CAR_CONTROL_GPRS_INTERNAL_TOKEN'),
        'commands_path' => ltrim((string) env('CAR_CONTROL_GPRS_COMMANDS_PATH', 'commands'), '/'),
        'device_status_path' => (string) env('CAR_CONTROL_GPRS_DEVICE_STATUS_PATH', 'devices/{imei}/status'),
        'command_timeout_seconds' => (int) env('CAR_CONTROL_GPRS_COMMAND_TIMEOUT', 30),
        'device_status_timeout_seconds' => (int) env('CAR_CONTROL_GPRS_DEVICE_STATUS_TIMEOUT', 5),
        'pair_command_delay_seconds' => (int) env('CAR_CONTROL_PAIR_GPRS_DELAY_SECONDS', 0),
    ],

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
    | Bare device command strings (Codec12 / device payload). SMS adds car_control.sms.command_prefix.
    | Override per action: CAR_CONTROL_COMMAND_* (preferred) or legacy CAR_CONTROL_SMS_* for the bare part.
    */
    'commands' => [
        'open_car' => env('CAR_CONTROL_COMMAND_OPEN', env('CAR_CONTROL_SMS_OPEN', 'lvcanopenalldoors')),
        'close_car' => env('CAR_CONTROL_COMMAND_CLOSE', env('CAR_CONTROL_SMS_CLOSE', 'lvcanclosealldoors')),
        'unlock_engine' => env('CAR_CONTROL_COMMAND_UNLOCK_ENGINE', env('CAR_CONTROL_SMS_UNLOCK_ENGINE', 'setdigout 00 0 0')),
        'lock_engine' => env('CAR_CONTROL_COMMAND_LOCK_ENGINE', env('CAR_CONTROL_SMS_LOCK_ENGINE', 'setdigout 10 0 0')),
    ],

    /*
    | SMS only: prepended to each bare command (GPRS Codec12 is sent without this prefix).
    | Set CAR_CONTROL_SMS_PREFIX= to disable.
    */
    'sms' => [
        'command_prefix' => trim((string) env('CAR_CONTROL_SMS_PREFIX', 'youto youto')),
    ],
];
