<?php

namespace Database\Factories;

use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Station>
 */
class StationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city() . ' Station';
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'address' => fake()->optional()->streetAddress(),
            'provider' => fake()->optional()->randomElement(['Elektrum', 'Eleport', 'Enefit', 'Ignitis']),
            'latitude' => fake()->optional()->latitude(56.8, 57.1),
            'longitude' => fake()->optional()->longitude(23.9, 24.3),
        ];
    }
}
