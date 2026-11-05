<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InventoryMovement> */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return ['material_id' => Material::factory(), 'folio' => 'LDR-DP-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 4, '0', STR_PAD_LEFT),
            'movement_type' => 'entry', 'quantity_original' => '1.000000', 'unit_original' => 'Kg', 'quantity_equivalent' => '1.000000',
            'signed_quantity' => '1.000000', 'stock_before' => '0.000000', 'stock_after' => '1.000000',
            'material_code_snapshot' => 'TEST', 'material_name_snapshot' => 'Material de prueba', 'unit_snapshot' => 'Kg',
            'occurred_at' => now(), 'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', Str::random()), 'source_kind' => 'manual'];
    }
}
