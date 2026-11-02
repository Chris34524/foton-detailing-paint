<?php

namespace Database\Factories;

use App\Models\Defect;
use App\Models\DefectEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DefectEvidence> */
class DefectEvidenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'defect_id' => Defect::factory(), 'disk' => 'diagnosis_evidence',
            'path' => 'test-evidence/'.Str::uuid().'.jpg', 'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'uploaded_by' => null,
        ];
    }
}
