<?php

namespace Database\Seeders;

use App\Enums\DriverStatus;
use App\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\ShiftPolicy;
use App\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FleetManagementSeeder extends Seeder
{
    public function run(): void
    {
        ShiftPolicy::firstOrCreate([], [
            'name' => 'Default',
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8, 10, 12],
            'vehicle_downtime_hours' => 0,
            'max_shifts_per_driver_per_day' => null,
            'planning_window_days' => 14,
            'time_slot_minutes' => 15,
            'timezone' => 'Europe/Riga',
        ]);

        $station1 = Station::firstOrCreate(
            ['slug' => 'riga-center'],
            ['name' => 'Riga Center', 'address' => null, 'is_active' => true]
        );
        $station2 = Station::firstOrCreate(
            ['slug' => 'airport'],
            ['name' => 'Airport', 'address' => null, 'is_active' => true]
        );

        FleetVehicle::firstOrCreate(
            ['registration_number' => 'REG-RC-001'],
            [
                'label' => 'Toyota Corolla (REG-RC-001)',
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2022,
                'home_station_id' => $station1->id,
                'status' => VehicleStatus::Active,
            ]
        );
        FleetVehicle::firstOrCreate(
            ['registration_number' => 'REG-RC-002'],
            [
                'label' => 'Tesla Model 3 (REG-RC-002)',
                'brand' => 'Tesla',
                'model' => 'Model 3',
                'year' => 2023,
                'home_station_id' => $station1->id,
                'status' => VehicleStatus::Active,
            ]
        );
        FleetVehicle::firstOrCreate(
            ['registration_number' => 'REG-AP-001'],
            [
                'label' => 'VW ID.4 (REG-AP-001)',
                'brand' => 'VW',
                'model' => 'ID.4',
                'year' => 2023,
                'home_station_id' => $station2->id,
                'status' => VehicleStatus::Active,
            ]
        );

        Driver::firstOrCreate(
            ['email' => 'driver@evodrive.lv'],
            [
                'name' => 'Test Driver',
                'first_name' => 'Test',
                'last_name' => 'Driver',
                'password' => Hash::make('password'),
                'license_number' => 'LIC-001',
                'locale' => 'en',
                'status' => DriverStatus::Active,
            ]
        );
    }
}
