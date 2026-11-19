<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\UnitRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitRelease> */
class UnitReleaseFactory extends Factory
{
    public function definition(): array
    {
        return ['inspection_id' => Inspection::factory()->state(['status' => 'completed', 'result' => 'passed']), 'unit_id' => fn (array $attributes) => Inspection::findOrFail($attributes['inspection_id'])->workOrder->diagnosis->unit_id, 'released_at' => now()];
    }
}
