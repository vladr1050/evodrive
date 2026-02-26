<?php

namespace Database\Factories;

use App\Models\RentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentalVehicleFactory extends Factory
{
    protected $model = RentalVehicle::class;

    public function definition(): array
    {
        $make = fake()->randomElement(['Toyota', 'Tesla', 'VW']);
        $model = $make === 'Toyota' ? 'Corolla' : ($make === 'Tesla' ? 'Model 3' : 'ID.4');

        return [
            'make' => $make,
            'model' => $model,
            'year' => fake()->numberBetween(2020, 2024),
            'type' => 'Sedan',
            'transmission' => 'Automatic',
            'consumption' => '5.5 l/100km',
            'seats' => 5,
            'price' => 299,
            'deposit' => 500,
            'image_path' => null,
            'image_url' => null,
            'categories' => [],
            'description' => [
                'en' => 'Reliable vehicle for taxi work.',
                'lv' => 'Uzticams transports taksi darbam.',
                'ru' => 'Надежный автомобиль для работы в такси.',
            ],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
