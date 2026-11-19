<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\Rework;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rework> */
class ReworkFactory extends Factory
{
    public function definition(): array
    {
        return ['inspection_id' => Inspection::factory()->state(['status' => 'completed', 'result' => 'failed']), 'work_order_id' => fn (array $attributes) => Inspection::findOrFail($attributes['inspection_id'])->work_order_id, 'cause' => 'Causa de prueba', 'status' => 'open'];
    }
}
