<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\Diagnosis;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    private function duplicate(): never
    {
        throw ValidationException::withMessages(['diagnosis' => 'Este diagnóstico ya tiene una orden de trabajo.']);
    }

    public function generate(Diagnosis $diagnosis, User $creator, ?string $notes): WorkOrder
    {
        try {
            return DB::transaction(function () use ($diagnosis, $creator, $notes): WorkOrder {
                $source = Diagnosis::query()->lockForUpdate()->findOrFail($diagnosis->id);
                if ($source->workOrder()->exists()) {
                    $this->duplicate();
                }
                $priorities = $source->defects()->lockForUpdate()->get()->pluck('priority');
                $priority = WorkOrderPriority::highest($priorities);
                if ($priority === null) {
                    throw ValidationException::withMessages(['diagnosis' => 'Agrega al menos un defecto antes de generar una orden de trabajo.']);
                }
                $order = new WorkOrder(['priority' => $priority, 'notes' => $notes]);
                $order->diagnosis()->associate($source);
                $order->createdBy()->associate($creator);
                $order->save();
                app(ProcessFlowService::class)->snapshot($order);

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            if (WorkOrder::query()->where('diagnosis_id', $diagnosis->id)->exists()) {
                $this->duplicate();
            }
            throw $error;
        }
    }

    private function active(WorkOrder $order): void
    {
        if ($order->status->isTerminal()) {
            throw ValidationException::withMessages(['work_order' => 'Una orden completada o cancelada es de solo lectura.']);
        }
    }

    public function update(WorkOrder $order, array $attributes): WorkOrder
    {
        return DB::transaction(function () use ($order, $attributes): WorkOrder {
            $current = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->active($current);
            $current->update($attributes);

            return $current;
        }, 3);
    }

    public function changeStatus(WorkOrder $order, WorkOrderStatus $status): WorkOrder
    {
        return DB::transaction(function () use ($order, $status): WorkOrder {
            $current = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->active($current);
            if (! in_array($status, $current->status->transitions(), true)) {
                throw ValidationException::withMessages(['status' => 'La transición de estado solicitada no está permitida.']);
            }
            if ($status === WorkOrderStatus::InProgress && $current->assigned_to === null) {
                throw ValidationException::withMessages(['status' => 'Asigna un operador antes de iniciar el trabajo.']);
            }
            $current->status = $status;
            $current->save();

            return $current;
        }, 3);
    }

    public function assign(WorkOrder $order, User $actor, ?int $operatorId): WorkOrder
    {
        return DB::transaction(function () use ($order, $actor, $operatorId): WorkOrder {
            $current = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->active($current);
            if ($operatorId === null && $current->status === WorkOrderStatus::InProgress) {
                throw ValidationException::withMessages(['assigned_to' => 'Una orden en proceso debe conservar un operador asignado.']);
            }
            $operator = $operatorId === null ? null : User::role(Role::Operator->value)->find($operatorId);
            if ($operatorId !== null && $operator === null) {
                throw ValidationException::withMessages(['assigned_to' => 'Selecciona un usuario con rol de operador.']);
            }
            $current->assignedTo()->associate($operator);
            $current->assignedBy()->associate($actor);
            $current->assigned_at = $operator === null ? null : now();
            $current->save();

            return $current;
        }, 3);
    }
}
