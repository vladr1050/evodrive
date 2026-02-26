<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'source' => 'unknown',
            'intent' => 'work',
            'phone' => '+3712' . fake()->numerify('#######'),
            'name' => fake()->name(),
            'atd_license' => true,
            'atd_number' => fake()->optional()->regexify('ATD-[0-9]{6}'),
            'driving_experience' => fake()->randomElement(['3-5', '5-10', '10+']),
            'area' => fake()->city(),
            'status' => 'new',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
