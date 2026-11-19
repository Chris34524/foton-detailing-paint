<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inspection> */
class InspectionFactory extends Factory
{
    public function definition(): array
    {
        return ['work_order_id' => WorkOrder::factory()->state(['status' => 'completed']), 'inspection_checklist_id' => InspectionChecklist::factory(), 'checklist_name_snapshot' => 'Checklist de prueba', 'status' => 'draft'];
    }
}
