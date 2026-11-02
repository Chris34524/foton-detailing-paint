<?php

namespace Database\Factories;

use App\Enums\WorkOrderPriority;
use App\Models\Diagnosis;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrder> */
class WorkOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['diagnosis_id' => Diagnosis::factory(), 'status' => 'queued',
            'priority' => fake()->randomElement(WorkOrderPriority::cases()), 'notes' => null];
    }
}
