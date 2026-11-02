<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vin' => 'LVBV3JBB7'.fake()->unique()->numerify('########'),
            'model' => fake()->randomElement(['S5', 'S6', 'TM']),
            'status' => UnitStatus::Registered,
            'current_location' => null,
        ];
    }
}
