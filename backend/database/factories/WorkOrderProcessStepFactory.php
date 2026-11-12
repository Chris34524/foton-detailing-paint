<?php

namespace Database\Factories;

use App\Models\Process;
use App\Models\WorkOrder;
use App\Models\WorkOrderProcessStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrderProcessStep> */
class WorkOrderProcessStepFactory extends Factory
{
    public function definition(): array
    {
        return ['work_order_id' => WorkOrder::factory(), 'process_id' => Process::factory(), 'process_code_snapshot' => 'TEST', 'process_name_snapshot' => 'Proceso de prueba', 'position' => 1];
    }
}
