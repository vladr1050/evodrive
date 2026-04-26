<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver fleet overview (Filament Driver Statistics)
    |--------------------------------------------------------------------------
    |
    | Activity score = normalized weights of:
    | - past: completed minutes on days ≤ today within the selected date range
    | - future: booked minutes on days > today within the range; if the range has
    |   no future calendar days, the same weight uses rolling booked hours from
    |   “today” for rolling_booked_anchor_days instead.
    | - reliability: cancellations vs booked+completed volume in the range.
    |
    */
    'driver_fleet_activity' => [
        'weight_past_completed' => (float) env('FLEET_INSIGHT_WEIGHT_PAST', 0.35),
        'weight_future_load' => (float) env('FLEET_INSIGHT_WEIGHT_FUTURE', 0.35),
        'weight_reliability' => (float) env('FLEET_INSIGHT_WEIGHT_RELIABILITY', 0.30),
        'rolling_booked_anchor_days' => (int) env('FLEET_INSIGHT_ROLLING_ANCHOR_DAYS', 30),
    ],

];
