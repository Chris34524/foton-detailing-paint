<?php

namespace Database\Factories;

use App\Enums\DefectPriority;
use App\Models\Defect;
use App\Models\DefectType;
use App\Models\Diagnosis;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Defect> */
class DefectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'diagnosis_id' => Diagnosis::factory(), 'defect_type_id' => DefectType::factory(),
            'location' => 'Puerta delantera derecha', 'description' => null,
            'priority' => DefectPriority::High, 'created_by' => null,
        ];
    }
}
