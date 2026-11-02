<?php

namespace Database\Factories;

use App\Models\DefectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectType> */
class DefectTypeFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Tipo de prueba ????-####'), 'description' => null, 'is_active' => true];
    }
}
