<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaterialConsumption> */
class MaterialConsumptionFactory extends Factory
{
    public function definition(): array
    {
        return ['material_id' => Material::factory(), 'work_order_id' => WorkOrder::factory(), 'material_code_snapshot' => 'MAT-TEST', 'material_name_snapshot' => 'Material de prueba', 'unit_snapshot' => 'kg', 'quantity' => '1.000', 'consumed_at' => now()];
    }
}
