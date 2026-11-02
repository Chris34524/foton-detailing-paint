<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Diagnosis> */
class DiagnosisFactory extends Factory
{
    public function definition(): array
    {
        return ['unit_id' => Unit::factory(), 'notes' => fake()->sentence(), 'diagnosed_by' => null];
    }
}
