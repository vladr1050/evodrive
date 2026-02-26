<?php

namespace Database\Factories;

use App\Models\ShiftPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShiftPolicy>
 */
class ShiftPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Default',
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8, 10, 12],
            'vehicle_downtime_hours' => 0,
            'max_shifts_per_driver_per_day' => null,
            'planning_window_days' => 14,
            'time_slot_minutes' => 15,
        ];
    }
}
