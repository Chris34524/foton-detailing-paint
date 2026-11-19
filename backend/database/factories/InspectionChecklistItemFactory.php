<?php

namespace Database\Factories;

use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionChecklistItem> */
class InspectionChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return ['inspection_checklist_id' => InspectionChecklist::factory(), 'code' => strtoupper(fake()->unique()->bothify('ITEM-####??')), 'label' => fake()->sentence(), 'sort_order' => 1, 'is_required' => true, 'is_active' => true];
    }
}
