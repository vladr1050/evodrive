<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'shifts_chat_id' => env('TELEGRAM_SHIFTS_CHAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Cancellation notification rate limit (anti-spam)
    |--------------------------------------------------------------------------
    | Max number of cancellation notifications per driver within the window.
    */
    'cancellation_notify_max_per_driver' => (int) env('TELEGRAM_CANCELLATION_NOTIFY_MAX_PER_DRIVER', 3),
    'cancellation_notify_rate_window_minutes' => (int) env('TELEGRAM_CANCELLATION_RATE_WINDOW_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Replacement shift tolerance (minutes)
    |--------------------------------------------------------------------------
    | If driver creates a new booked shift at same station within this many
    | minutes of the cancelled slot, we consider it a "replacement" and do not notify.
    */
    'replacement_tolerance_minutes' => (int) env('TELEGRAM_REPLACEMENT_TOLERANCE_MINUTES', 15),
];
