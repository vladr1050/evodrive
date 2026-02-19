<?php

namespace Database\Seeders;

use App\Models\RentalVehicle;
use Illuminate\Database\Seeder;

class RentalVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $cars = config('rental_cars', []);

        foreach ($cars as $i => $car) {
            RentalVehicle::firstOrCreate(
                [
                    'make' => $car['make'],
                    'model' => $car['model'],
                    'year' => $car['year'],
                ],
                [
                    'type' => $car['type'],
                    'transmission' => $car['transmission'],
                    'consumption' => $car['consumption'],
                    'seats' => $car['seats'] ?? 5,
                    'price' => $car['price'],
                    'deposit' => $car['deposit'],
                    'image_path' => null,
                    'image_url' => $car['image'] ?? null,
                    'categories' => $car['categories'] ?? [],
                    'description' => [
                        'en' => $car['description'] ?? null,
                        'ru' => $car['description'] ?? null,
                        'lv' => $car['description'] ?? null,
                    ],
                    'sort_order' => $i,
                    'is_active' => true,
                ]
            );
        }
    }
}
