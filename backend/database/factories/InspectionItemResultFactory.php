<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionItemResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionItemResult> */
class InspectionItemResultFactory extends Factory
{
    public function definition(): array
    {
        return ['inspection_id' => Inspection::factory(), 'item_code_snapshot' => 'ITEM', 'item_label_snapshot' => 'Criterio de prueba', 'sort_order_snapshot' => 1, 'is_required_snapshot' => true];
    }
}
