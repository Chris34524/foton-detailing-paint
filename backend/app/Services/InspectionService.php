<?php

namespace App\Services;

use App\Enums\InspectionItemCompliance;
use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use App\Enums\ReworkStatus;
use App\Enums\UnitStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\InspectionItemResult;
use App\Models\Rework;
use App\Models\Unit;
use App\Models\UnitRelease;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionService
{
    private function lockOrder(int $id, int $unitId): WorkOrder
    {
        Unit::query()->whereKey($unitId)->lockForUpdate()->firstOrFail();

        return WorkOrder::query()->lockForUpdate()->findOrFail($id);
    }

    private function completedOrder(WorkOrder $order): void
    {
        if ($order->status !== WorkOrderStatus::Completed) {
            throw ValidationException::withMessages(['work_order' => 'Solo puedes inspeccionar una orden de trabajo completada.']);
        }
    }

    public function create(WorkOrder $order, User $actor, array $data): Inspection
    {
        $unitId = $order->diagnosis->unit_id;
        try {
            return DB::transaction(function () use ($order, $actor, $data, $unitId): Inspection {
                $current = $this->lockOrder($order->id, $unitId);
                $this->completedOrder($current);
                if ($current->inspections()->where('status', InspectionStatus::Draft)->exists()) {
                    throw ValidationException::withMessages(['inspection' => 'Esta orden ya tiene una inspección en borrador.']);
                }
                $checklist = InspectionChecklist::query()->lockForUpdate()->find($data['inspection_checklist_id']);
                if ($checklist === null || ! $checklist->is_active) {
                    throw ValidationException::withMessages(['inspection_checklist_id' => 'Selecciona un checklist activo.']);
                }
                $items = $checklist->items()->where('is_active', true)->get();
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['inspection_checklist_id' => 'El checklist debe tener al menos un criterio activo.']);
                }
                $inspection = new Inspection;
                $inspection->work_order_id = $current->id;
                $inspection->inspection_checklist_id = $checklist->id;
                $inspection->checklist_name_snapshot = $checklist->name;
                $inspection->status = InspectionStatus::Draft;
                $inspection->inspected_by = $actor->id;
                $inspection->notes = $data['notes'] ?? null;
                $inspection->save();
                foreach ($items as $item) {
                    $result = new InspectionItemResult;
                    $result->inspection_id = $inspection->id;
                    $result->checklist_item_id = $item->id;
                    $result->item_code_snapshot = $item->code;
                    $result->item_label_snapshot = $item->label;
                    $result->item_description_snapshot = $item->description;
                    $result->sort_order_snapshot = $item->sort_order;
                    $result->is_required_snapshot = $item->is_required;
                    $result->save();
                }

                return $inspection;
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['inspection' => 'Esta orden ya tiene una inspección en borrador.']);
        }
    }

    private function draft(Inspection $inspection): void
    {
        if ($inspection->status !== InspectionStatus::Draft) {
            throw ValidationException::withMessages(['inspection' => 'La inspección completada es de solo lectura.']);
        }
    }

    public function update(Inspection $inspection, array $data): Inspection
    {
        $unitId = $inspection->workOrder->diagnosis->unit_id;

        return DB::transaction(function () use ($inspection, $data, $unitId): Inspection {
            $this->lockOrder($inspection->work_order_id, $unitId);
            $current = Inspection::query()->lockForUpdate()->findOrFail($inspection->id);
            $this->draft($current);
            if (array_key_exists('notes', $data)) {
                $current->notes = $data['notes'];
                $current->save();
            }
            foreach ($data['items'] ?? [] as $attributes) {
                $item = $current->items()->whereKey($attributes['id'])->first();
                if ($item === null) {
                    throw ValidationException::withMessages(['items' => 'Cada respuesta debe pertenecer a esta inspección.']);
                }
                if (array_key_exists('result', $attributes)) {
                    $item->result = $attributes['result'];
                }
                if (array_key_exists('comment', $attributes)) {
                    $item->comment = is_string($attributes['comment']) ? (trim($attributes['comment']) ?: null) : null;
                }
                $item->save();
            }

            return $current;
        }, 3);
    }

    public function complete(Inspection $inspection, User $actor, ?string $cause): Inspection
    {
        $unitId = $inspection->workOrder->diagnosis->unit_id;

        return DB::transaction(function () use ($inspection, $actor, $cause, $unitId): Inspection {
            $order = $this->lockOrder($inspection->work_order_id, $unitId);
            $this->completedOrder($order);
            $current = Inspection::query()->lockForUpdate()->findOrFail($inspection->id);
            $this->draft($current);
            $required = $current->items()->where('is_required_snapshot', true)->get();
            if ($required->contains(fn (InspectionItemResult $item): bool => $item->result === null)) {
                throw ValidationException::withMessages(['items' => 'Responde todos los criterios obligatorios antes de completar.']);
            }
            $failed = $required->contains(fn (InspectionItemResult $item): bool => $item->result === InspectionItemCompliance::NonCompliant);
            if ($failed && ($cause === null || trim($cause) === '')) {
                throw ValidationException::withMessages(['rework_cause' => 'Indica la causa de retrabajo para los criterios que no cumplen.']);
            }
            $current->status = InspectionStatus::Completed;
            $current->result = $failed ? InspectionResult::Failed : InspectionResult::Passed;
            $current->completed_at = now();
            $current->inspected_by = $actor->id;
            $current->save();
            if ($failed) {
                $rework = new Rework;
                $rework->inspection_id = $current->id;
                $rework->work_order_id = $order->id;
                $rework->cause = $cause;
                $rework->status = ReworkStatus::Open;
                $rework->created_by = $actor->id;
                $rework->save();
            } else {
                $order->reworks()->where('status', ReworkStatus::Open)->update(['status' => ReworkStatus::Resolved, 'resolved_at' => now(), 'updated_at' => now()]);
            }
            $unit = $order->diagnosis->unit;
            $unit->status = $failed ? UnitStatus::Rework : UnitStatus::ReadyForRelease;
            $unit->save();

            return $current;
        }, 3);
    }

    /** @return array<string,bool> */
    public function releaseChecks(Inspection $inspection): array
    {
        $order = $inspection->workOrder;
        $latest = $order->inspections()->where('status', InspectionStatus::Completed)->orderByDesc('completed_at')->orderByDesc('id')->value('id');

        return [
            'inspection_completed' => $inspection->status === InspectionStatus::Completed,
            'inspection_passed' => $inspection->result === InspectionResult::Passed,
            'latest_completed_inspection' => $latest === $inspection->id,
            'work_order_completed' => $order->status === WorkOrderStatus::Completed,
            'no_open_rework' => ! $order->reworks()->where('status', ReworkStatus::Open)->exists(),
            'not_released' => ! $inspection->release()->exists(),
        ];
    }

    public function release(Inspection $inspection, User $actor, ?string $notes): UnitRelease
    {
        $unitId = $inspection->workOrder->diagnosis->unit_id;
        try {
            return DB::transaction(function () use ($inspection, $actor, $notes, $unitId): UnitRelease {
                $this->lockOrder($inspection->work_order_id, $unitId);
                $current = Inspection::query()->lockForUpdate()->findOrFail($inspection->id);
                $checks = $this->releaseChecks($current);
                if (in_array(false, $checks, true)) {
                    throw ValidationException::withMessages(['release' => 'La liberación requiere la última inspección completada y aprobada, orden completada, sin retrabajos abiertos y sin liberación previa.']);
                }
                $release = new UnitRelease;
                $release->unit_id = $current->workOrder->diagnosis->unit_id;
                $release->inspection_id = $current->id;
                $release->released_by = $actor->id;
                $release->released_at = now();
                $release->notes = $notes;
                $release->save();
                $unit = $release->unit;
                $unit->status = UnitStatus::Released;
                $unit->save();

                return $release;
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['release' => 'Esta inspección ya tiene una liberación registrada.']);
        }
    }
}
