<?php

namespace Database\Factories;

use App\Models\InventoryBalance;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryBalance> */
class InventoryBalanceFactory extends Factory
{
    public function definition(): array
    {
        return ['material_id' => Material::factory(), 'quantity' => '0.000000'];
    }
}
