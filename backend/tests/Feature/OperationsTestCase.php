<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistItem;
use App\Models\Material;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InspectionService;
use App\Services\InventoryMovementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

abstract class OperationsTestCase extends DiagnosisTestCase
{
    protected function fundMaterial(Material $material, string $quantity = '100'): void
    {
        $actor = User::factory()->create()->assignRole(Role::Admin);
        app(InventoryMovementService::class)->confirm($actor, [
            'material_id' => $material->id, 'movement_type' => 'opening_balance', 'quantity_original' => $quantity, 'unit_original' => $material->unit,
            'idempotency_key' => (string) Str::uuid()]);
    }

    protected function checklist(): InspectionChecklist
    {
        $checklist = InspectionChecklist::factory()->create();
        InspectionChecklistItem::factory()->for($checklist, 'checklist')->create(['code' => 'REQUIRED', 'sort_order' => 1]);
        InspectionChecklistItem::factory()->for($checklist, 'checklist')->create(['code' => 'OPTIONAL', 'sort_order' => 2, 'is_required' => false]);

        return $checklist;
    }

    protected function draft(?WorkOrder $order = null, ?InspectionChecklist $checklist = null): Inspection
    {
        $order ??= WorkOrder::factory()->create(['status' => 'completed']);
        $checklist ??= $this->checklist();

        return app(InspectionService::class)->create($order, Auth::user() ?? User::factory()->create(), ['inspection_checklist_id' => $checklist->id]);
    }

    protected function answer(Inspection $inspection, string $result = 'compliant'): void
    {
        $inspection->items()->update(['result' => $result]);
    }
}
