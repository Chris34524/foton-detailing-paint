<?php

namespace Tests\Feature;

use App\Models\MaterialConsumption;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationsUpgradeTest extends TestCase
{
    public function test_phase_five_data_and_legacy_consumption_survive_additive_upgrade(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.operations_upgrade' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('operations_upgrade');
        Schema::clearResolvedInstance('db.schema');
        try {
            foreach (glob(database_path('migrations/*.php')) as $file) {
                if (basename($file) < '2026_11_03') {
                    (require $file)->up();
                }
            }
            $order = WorkOrder::factory()->create();
            $orderBefore = (array) DB::table('work_orders')->find($order->id);
            $unit = $order->diagnosis->unit;
            $unitBefore = (array) DB::table('units')->find($unit->id);
            (require database_path('migrations/2026_11_03_050251_create_operations_tables.php'))->up();
            $materialId = DB::table('materials')->insertGetId([
                'code' => 'LEGACY', 'name' => 'Existing material', 'unit' => 'kg', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $consumptionId = DB::table('material_consumptions')->insertGetId([
                'material_id' => $materialId, 'work_order_id' => $order->id,
                'material_code_snapshot' => 'LEGACY', 'material_name_snapshot' => 'Existing material',
                'unit_snapshot' => 'kg', 'quantity' => '1.750', 'consumed_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $migration = require database_path('migrations/2026_11_03_164056_add_material_inventory_control.php');
            $migration->up();
            $this->assertSame($unitBefore, (array) DB::table('units')->find($unit->id));
            $orderAfter = (array) DB::table('work_orders')->find($order->id);
            unset($orderAfter['current_process_step_id']);
            $this->assertSame($orderBefore, $orderAfter);
            $this->assertNull($order->fresh()->current_process_step_id);
            $this->assertSame('Kg', DB::table('materials')->where('id', $materialId)->value('unit'));
            $legacy = MaterialConsumption::findOrFail($consumptionId);
            $this->assertSame('1.750000', $legacy->quantity);
            $this->assertSame('kg', $legacy->unit_snapshot);
            $this->assertNull($legacy->inventory_movement_id);
            $this->assertSame($unit->vin, $legacy->workOrder->diagnosis->unit->vin);
            $this->assertSame(0, DB::table('inventory_movements')->count());
            $this->assertSame(0, DB::table('inventory_balances')->count());

            $migration->down();
            $this->assertFalse(Schema::hasTable('inventory_movements'));
            $this->assertFalse(Schema::hasColumn('material_consumptions', 'inventory_movement_id'));
            $this->assertSame(1, DB::table('material_consumptions')->count());
            $migration->up();
            $this->assertNull(MaterialConsumption::findOrFail($consumptionId)->inventory_movement_id);
        } finally {
            DB::setDefaultConnection($previous);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('operations_upgrade');
        }
    }
}
