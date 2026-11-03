<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\MaterialUnit;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    public const AREAS = ['Pintura 1', 'Pintura 2', 'Lavado'];

    public function __construct(private MaterialQuantityConverter $converter) {}

    private function mismatch(): never
    {
        throw ValidationException::withMessages(['idempotency_key' => 'La clave de reintento ya se utilizó para una operación distinta.']);
    }

    private function fingerprint(User $actor, ?WorkOrder $order, array $data): string
    {
        return hash('sha256', json_encode([$actor->id, $order?->id, $data], JSON_THROW_ON_ERROR));
    }

    /** @return array{movement:InventoryMovement,consumption:?MaterialConsumption,replayed:bool} */
    private function replay(InventoryMovement $movement, string $fingerprint): array
    {
        if (! hash_equals($movement->request_fingerprint, $fingerprint)) {
            $this->mismatch();
        }

        return ['movement' => $movement, 'consumption' => $movement->consumption, 'replayed' => true];
    }

    /** @return array{movement:InventoryMovement,consumption:?MaterialConsumption,replayed:bool} */
    public function confirm(User $actor, array $input, ?WorkOrder $workOrder = null): array
    {
        $type = $workOrder === null ? InventoryMovementType::from($input['movement_type']) : InventoryMovementType::Exit;
        $workOrder === null ? Gate::forUser($actor)->authorize('create', [InventoryMovement::class, $type])
            : Gate::forUser($actor)->authorize('create', [MaterialConsumption::class, $workOrder]);
        $key = mb_strtolower($input['idempotency_key']);
        $fingerprint = null;
        try {
            return DB::transaction(function () use ($actor, $input, $workOrder, $type, $key, &$fingerprint): array {
                $order = $workOrder === null ? null : WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
                $material = Material::query()->lockForUpdate()->find($input['material_id']);
                if ($material === null) {
                    throw ValidationException::withMessages(['material_id' => 'Selecciona un material válido.']);
                }
                $originalUnit = MaterialUnit::normalize($input['unit_original'] ?? $material->unit);
                $quantity = $this->converter->amount($workOrder === null ? $input['quantity_original'] : $input['quantity'], true, $workOrder === null ? 'quantity_original' : 'quantity');
                $data = ['material_id' => $material->id, 'movement_type' => $type->value, 'quantity_original' => $quantity, 'unit_original' => $originalUnit?->value,
                    'area_destination' => $input['area_destination'] ?? null, 'notes' => $input['notes'] ?? null,
                    'process_step_id' => isset($input['work_order_process_step_id']) ? (int) $input['work_order_process_step_id'] : null, 'external_reference' => $input['external_reference'] ?? null];
                $fingerprint = $this->fingerprint($actor, $order, $data);
                $existing = InventoryMovement::query()->where('idempotency_key', $key)->first();
                if ($existing !== null) {
                    return $this->replay($existing, $fingerprint);
                }
                if (! $material->is_active) {
                    throw ValidationException::withMessages(['material_id' => 'Selecciona un material activo.']);
                }
                if ($order !== null && $order->status->isTerminal()) {
                    throw ValidationException::withMessages(['work_order' => 'No puedes registrar consumo en una orden completada o cancelada.']);
                }
                if ($order !== null && $data['process_step_id'] !== null && ! $order->processSteps()->whereKey($data['process_step_id'])->exists()) {
                    throw ValidationException::withMessages(['work_order_process_step_id' => 'El proceso debe pertenecer a esta orden de trabajo.']);
                }
                if ($type->isAdjustment() && ($data['notes'] === null || trim($data['notes']) === '')) {
                    throw ValidationException::withMessages(['notes' => 'Los ajustes requieren una justificación.']);
                }
                if ($data['area_destination'] !== null && ! in_array($data['area_destination'], self::AREAS, true)) {
                    throw ValidationException::withMessages(['area_destination' => 'Selecciona un área o destino del catálogo.']);
                }
                $equivalent = $this->converter->convert($quantity, $originalUnit?->value ?? '', $material->unit);
                $balance = InventoryBalance::query()->where('material_id', $material->id)->lockForUpdate()->first();
                $latest = $material->inventoryMovements()->orderByDesc('id')->lockForUpdate()->first();
                if (bccomp($balance?->quantity ?? '0', $latest?->stock_after ?? '0', 6) !== 0) {
                    throw ValidationException::withMessages(['inventory' => 'El saldo requiere conciliación con el historial antes de registrar nuevos movimientos.']);
                }
                if ($balance === null) {
                    $balance = new InventoryBalance;
                    $balance->material_id = $material->id;
                    $balance->save();
                }
                if ($type === InventoryMovementType::OpeningBalance && $material->inventoryMovements()->exists()) {
                    throw ValidationException::withMessages(['movement_type' => 'La apertura solo puede registrarse una vez y antes de otros movimientos. Usa un ajuste justificado para corregir existencias.']);
                }
                $signed = $type->increases() ? $equivalent : bcsub('0', $equivalent, 6);
                $after = bcadd($balance->quantity, $signed, 6);
                if (bccomp($after, '0', 6) < 0) {
                    throw ValidationException::withMessages(['quantity_original' => 'Existencia insuficiente. La operación no puede dejar saldo negativo.']);
                }
                if (bccomp($after, MaterialQuantityConverter::MAX, 6) > 0) {
                    throw ValidationException::withMessages(['quantity_original' => 'La existencia resultante supera el límite de inventario.']);
                }
                $column = $type->totalColumn();
                $total = bcadd($balance->{$column}, $equivalent, 6);
                if (bccomp($total, MaterialQuantityConverter::MAX, 6) > 0) {
                    throw ValidationException::withMessages(['quantity_original' => 'El total acumulado supera el límite de inventario.']);
                }
                $counter = DB::table('inventory_sequences')->where('name', 'movement_folio')->lockForUpdate()->first();
                $number = (string) $counter->next_value;
                DB::table('inventory_sequences')->where('name', 'movement_folio')->update(['next_value' => bcadd($number, '1', 0)]);
                $movement = new InventoryMovement;
                $movement->folio = 'LDR-DP-'.str_pad($number, 4, '0', STR_PAD_LEFT);
                $movement->material_id = $material->id;
                $movement->movement_type = $type;
                $movement->quantity_original = $quantity;
                $movement->unit_original = $originalUnit->value;
                $movement->quantity_equivalent = $equivalent;
                $movement->signed_quantity = $signed;
                $movement->stock_before = $balance->quantity;
                $movement->stock_after = $after;
                $movement->material_code_snapshot = $material->code;
                $movement->material_name_snapshot = $material->name;
                $movement->unit_snapshot = $material->unit;
                $movement->area_destination = $data['area_destination'];
                $movement->notes = $data['notes'];
                $movement->performed_by = $actor->id;
                $movement->occurred_at = now();
                $movement->idempotency_key = $key;
                $movement->request_fingerprint = $fingerprint;
                $movement->source_kind = $order === null ? 'manual' : 'consumption';
                $movement->save();
                $consumption = null;
                if ($order !== null) {
                    $consumption = new MaterialConsumption;
                    $consumption->material_id = $material->id;
                    $consumption->work_order_id = $order->id;
                    $consumption->work_order_process_step_id = $data['process_step_id'];
                    $consumption->inventory_movement_id = $movement->id;
                    $consumption->material_code_snapshot = $material->code;
                    $consumption->material_name_snapshot = $material->name;
                    $consumption->unit_snapshot = $material->unit;
                    $consumption->quantity = $equivalent;
                    $consumption->notes = $data['notes'];
                    $consumption->external_reference = $data['external_reference'];
                    $consumption->consumed_by = $actor->id;
                    $consumption->consumed_at = $movement->occurred_at;
                    $consumption->save();
                }
                $balance->quantity = $after;
                $balance->{$column} = $total;
                $balance->save();
                $material->touch();

                return ['movement' => $movement, 'consumption' => $consumption, 'replayed' => false];
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            $existing = InventoryMovement::query()->where('idempotency_key', $key)->first();
            if ($existing !== null && $fingerprint !== null) {
                return $this->replay($existing, $fingerprint);
            }
            if ($type === InventoryMovementType::OpeningBalance) {
                throw ValidationException::withMessages(['movement_type' => 'El material ya tiene apertura de inventario.']);
            }
            throw $error;
        }
    }
}
