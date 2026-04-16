<?php

namespace Database\Factories;

use App\Models\Renter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Renter>
 */
class RenterFactory extends Factory
{
    protected $model = Renter::class;

    public function definition(): array
    {
        return [
            'name_or_company' => fake()->company(),
            'is_active' => true,
        ];
    }
}
