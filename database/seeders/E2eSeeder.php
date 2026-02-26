<?php

namespace Database\Seeders;

use App\Enums\DriverStatus;
use App\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\RentalVehicle;
use App\Models\ShiftPolicy;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SiteSetting;
use App\Models\Station;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Minimal seed data for E2E tests. Run with DB_DATABASE=database/e2e.sqlite
 * Uses Carbon::setTestNow for deterministic dates during seeding.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-02-26 12:00:00', 'Europe/Riga'));

        try {
            $this->seedData();
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function seedData(): void
    {
        SiteSetting::firstOrCreate([], [
            'logo_path' => null,
            'favicon_path' => null,
        ]);

        foreach (['google_landing', 'meta_landing'] as $key) {
            $page = Page::firstOrCreate(
                ['key' => $key],
                [
                    'title' => ['en' => 'Test', 'lv' => 'Test', 'ru' => 'Тест'],
                    'slug' => ['en' => $key === 'google_landing' ? 'g' : 'm', 'lv' => 'g', 'ru' => 'g'],
                    'meta_title' => [],
                    'is_active' => true,
                ]
            );
            PageSection::firstOrCreate(
                ['page_id' => $page->id, 'key' => 'hero'],
                ['content' => ['en' => ['h1' => 'Test']], 'sort_order' => 1]
            );
        }

        ShiftPolicy::firstOrCreate([], [
            'name' => 'Default',
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8, 10, 12],
            'vehicle_downtime_hours' => 0,
            'max_shifts_per_driver_per_day' => null,
            'planning_window_days' => 14,
            'time_slot_minutes' => 15,
            'timezone' => 'Europe/Riga', // Explicit timezone for deterministic E2E
        ]);

        $station = Station::firstOrCreate(
            ['slug' => 'riga-center'],
            ['name' => 'Riga Center', 'address' => null, 'is_active' => true]
        );

        FleetVehicle::firstOrCreate(
            ['registration_number' => 'REG-E2E-001'],
            [
                'label' => 'Toyota Corolla (REG-E2E-001)',
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2022,
                'home_station_id' => $station->id,
                'status' => VehicleStatus::Active,
            ]
        );

        Driver::firstOrCreate(
            ['email' => 'driver@evodrive.lv'],
            [
                'name' => 'E2E Test Driver',
                'first_name' => 'E2E',
                'last_name' => 'Driver',
                'password' => Hash::make('password'),
                'license_number' => 'LIC-E2E',
                'locale' => 'en',
                'status' => DriverStatus::Active,
            ]
        );

        RentalVehicle::firstOrCreate(
            ['make' => 'Tesla', 'model' => 'Model 3', 'year' => 2023],
            [
                'type' => 'Sedan',
                'transmission' => 'Automatic',
                'consumption' => '5 l/100km',
                'seats' => 5,
                'price' => 299,
                'deposit' => 500,
                'image_path' => null,
                'image_url' => null,
                'categories' => [],
                'description' => ['en' => 'Tesla for rent', 'lv' => 'Tesla nomas', 'ru' => 'Tesla в аренду'],
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@evodrive.lv'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
    }
}
