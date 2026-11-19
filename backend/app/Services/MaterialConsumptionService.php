<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\MaterialConsumption;
use App\Models\User;
use App\Models\WorkOrder;

class MaterialConsumptionService
{
    public function __construct(private InventoryMovementService $inventory) {}

    /** @return array{movement:InventoryMovement,consumption:?MaterialConsumption,replayed:bool} */
    public function consume(WorkOrder $order, User $actor, array $data): array
    {
        return $this->inventory->confirm($actor, $data, $order);
    }
}
