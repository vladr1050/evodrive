<?php

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Models\FleetVehicle;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FleetVehicle>
 */
class FleetVehicleFactory extends Factory
{
    public function definition(): array
    {
        $brand = fake()->randomElement(['Toyota', 'Tesla', 'VW']);
        $model = $brand === 'Toyota' ? 'Corolla' : ($brand === 'Tesla' ? 'Model 3' : 'ID.4');
        $reg = 'REG-' . fake()->unique()->regexify('[A-Z]{2}[0-9]{4}');
        return [
            'label' => "{$brand} {$model} ({$reg})",
            'brand' => $brand,
            'model' => $model,
            'year' => fake()->numberBetween(2020, 2024),
            'color' => fake()->optional()->safeColorName(),
            'atd_license_number' => null,
            'registration_number' => $reg,
            'home_station_id' => Station::factory(),
            'status' => VehicleStatus::Active,
        ];
    }
}
