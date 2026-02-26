<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_duration_hours',
        'allowed_durations_json',
        'vehicle_downtime_hours',
        'max_shifts_per_driver_per_day',
        'planning_window_days',
        'time_slot_minutes',
        'require_return_to_home_station',
        'planning_opens_weekday',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'min_duration_hours' => 'integer',
            'allowed_durations_json' => 'array',
            'vehicle_downtime_hours' => 'float',
            'max_shifts_per_driver_per_day' => 'integer',
            'planning_window_days' => 'integer',
            'time_slot_minutes' => 'integer',
            'require_return_to_home_station' => 'boolean',
            'planning_opens_weekday' => 'integer',
        ];
    }

    public static function active(): ?self
    {
        return self::first();
    }

    public function allowedDurations(): array
    {
        return $this->allowed_durations_json ?? [4, 6, 8, 10, 12];
    }
}
