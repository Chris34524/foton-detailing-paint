<?php

namespace Database\Factories;

use App\Models\InspectionChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionChecklist> */
class InspectionChecklistFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'is_active' => true];
    }
}
