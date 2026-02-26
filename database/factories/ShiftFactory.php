<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = Carbon::today()->addHours(8);
        $endsAt = $startsAt->copy()->addHours(8);
        return [
            'driver_id' => Driver::factory(),
            'vehicle_id' => FleetVehicle::factory(),
            'station_id' => Station::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Booked,
        ];
    }
}
