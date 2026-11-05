<?php

namespace App\Services;

use App\Models\Process;
use App\Models\ProcessSequenceStep;
use App\Models\WorkOrder;
use App\Models\WorkOrderProcessStep;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessFlowService
{
    public function lockSequence(): void
    {
        DB::table('operation_mutexes')->where('name', 'process_sequence')->lockForUpdate()->first();
    }

    public function replace(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            $this->lockSequence();
            $processes = Process::query()->whereIn('id', $ids)->where('is_active', true)->lockForUpdate()->get();
            if ($processes->count() !== count($ids) || count(array_unique($ids)) !== count($ids)) {
                throw ValidationException::withMessages(['process_ids' => 'Selecciona procesos activos sin duplicados.']);
            }
            ProcessSequenceStep::query()->delete();
            foreach ($ids as $index => $id) {
                $step = new ProcessSequenceStep;
                $step->process_id = $id;
                $step->position = $index + 1;
                $step->save();
            }
        }, 3);
    }

    public function snapshot(WorkOrder $order, bool $required = false): void
    {
        $this->lockSequence();
        if ($order->processSteps()->exists()) {
            throw ValidationException::withMessages(['process_flow' => 'La secuencia de esta orden ya está inicializada.']);
        }
        $sequence = ProcessSequenceStep::query()->with(['process' => fn (BelongsTo $process) => $process->lockForUpdate()])->orderBy('position')->lockForUpdate()->get();
        if ($sequence->isEmpty() && $required) {
            throw ValidationException::withMessages(['process_flow' => 'Configura una secuencia de procesos antes de inicializar.']);
        }
        $first = null;
        foreach ($sequence as $entry) {
            if (! $entry->process->is_active) {
                throw ValidationException::withMessages(['process_flow' => 'La secuencia contiene un proceso inactivo. Revisa la configuración.']);
            }
            $step = new WorkOrderProcessStep;
            $step->work_order_id = $order->id;
            $step->process_id = $entry->process_id;
            $step->process_code_snapshot = $entry->process->code;
            $step->process_name_snapshot = $entry->process->name;
            $step->position = $entry->position;
            $step->save();
            $first ??= $step->id;
        }
        $order->current_process_step_id = $first;
        $order->save();
    }

    public function initialize(WorkOrder $order): WorkOrder
    {
        return DB::transaction(function () use ($order): WorkOrder {
            $current = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->snapshot($current, true);

            return $current;
        }, 3);
    }

    public function updateProcess(Process $process, array $attributes): Process
    {
        return DB::transaction(function () use ($process, $attributes): Process {
            $this->lockSequence();
            $current = Process::query()->lockForUpdate()->findOrFail($process->id);
            if (isset($attributes['is_active']) && ! $attributes['is_active'] && $current->sequenceStep()->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Retira el proceso de la secuencia antes de desactivarlo.']);
            }
            $current->update($attributes);

            return $current;
        }, 3);
    }
}
