<?php

namespace Database\Factories;

use App\Models\Process;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Process> */
class ProcessFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('PROC-####??')), 'name' => fake()->words(3, true), 'is_active' => true];
    }
}
