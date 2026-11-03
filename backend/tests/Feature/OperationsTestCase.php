<?php

namespace Tests\Feature;

use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InspectionService;
use Illuminate\Support\Facades\Auth;

abstract class OperationsTestCase extends DiagnosisTestCase
{
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
