<?php

namespace Database\Factories;

use App\Models\Process;
use App\Models\ProcessSequenceStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcessSequenceStep> */
class ProcessSequenceStepFactory extends Factory
{
    public function definition(): array
    {
        return ['process_id' => Process::factory(), 'position' => fake()->unique()->numberBetween(1, 1000000)];
    }
}
