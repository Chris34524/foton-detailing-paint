<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockStatus;
use App\Enums\MaterialUnit;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\InventoryMovementIndexRequest;
use App\Http\Requests\Operations\InventoryMovementRequest;
use App\Http\Requests\Operations\OperationsIndexRequest;
use App\Http\Requests\Operations\QuantityPreviewRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\MaterialResource;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Services\InventoryMovementService;
use App\Services\InventoryStockService;
use App\Services\MaterialQuantityConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    private function relations(): array
    {
        return ['performedBy', 'consumption.workOrder.diagnosis.unit'];
    }

    public function meta(Request $request): JsonResponse
    {
        Gate::authorize(Permission::MaterialsView->value);
        $types = array_map(fn (InventoryMovementType $type): array => ['value' => $type->value, 'label' => $type->label(), 'increases' => $type->increases(), 'requires_justification' => $type->isAdjustment()], InventoryMovementType::cases());

        return response()->json([
            'units' => array_map(fn (MaterialUnit $unit): array => ['value' => $unit->value, 'label' => $unit->value, 'family' => $unit->family(), 'factor' => $unit->factor()], MaterialUnit::cases()),
            'movement_types' => $types,
            'allowed_movement_types' => array_values(array_filter($types, fn (array $type): bool => $request->user()->can('create', [InventoryMovement::class, InventoryMovementType::from($type['value'])]))),
            'areas' => InventoryMovementService::AREAS,
            'stock_statuses' => array_map(fn (InventoryStockStatus $status): array => ['value' => $status->value, 'label' => $status->label(), 'recommended_action' => $status->action()], InventoryStockStatus::cases()),
            'quantity_scale' => 6, 'reorder_band_percentage' => '20',
        ]);
    }

    public function stock(Material $material, InventoryStockService $stock): MaterialResource
    {
        Gate::authorize('view', $material);

        return (new MaterialResource($material->load(['createdBy', 'inventoryBalance'])->loadExists(['inventoryMovements', 'materialConsumptions'])))
            ->additional(['reconciliation' => $stock->reconcile($material)]);
    }

    public function preview(QuantityPreviewRequest $request, Material $material, MaterialQuantityConverter $converter): JsonResponse
    {
        $data = $request->validated();
        $equivalent = $converter->convert($data['quantity_original'], $data['unit_original'], $material->unit);
        $type = InventoryMovementType::from($data['movement_type'] ?? InventoryMovementType::Exit->value);
        $before = $material->inventoryBalance?->quantity ?? '0.000000';
        $after = $type->increases() ? bcadd($before, $equivalent, 6) : bcsub($before, $equivalent, 6);
        $sufficient = bccomp($after, '0', 6) >= 0 && bccomp($after, MaterialQuantityConverter::MAX, 6) <= 0;
        $openingAllowed = $type !== InventoryMovementType::OpeningBalance || ! $material->inventoryMovements()->exists();

        return response()->json(['quantity_equivalent' => $equivalent, 'unit_inventory' => $material->unit, 'stock_before' => $before,
            'stock_after' => $sufficient ? $after : null, 'sufficient_stock' => $sufficient, 'opening_allowed' => $openingAllowed,
            'can_confirm' => $material->is_active && $sufficient && $openingAllowed && $request->user()->can('create', [InventoryMovement::class, $type])]);
    }

    public function index(InventoryMovementIndexRequest $request, ?Material $material = null): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = InventoryMovement::query()->with($this->relations());
        if ($material !== null) {
            $query->where('material_id', $material->id);
        }
        foreach (['material_id', 'movement_type', 'area_destination'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('material_code_snapshot', 'like', $term)->orWhere('material_name_snapshot', 'like', $term)->orWhere('folio', 'like', $term));
        }
        if (isset($filters['date_from'])) {
            $query->where('occurred_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if (isset($filters['date_to'])) {
            $query->where('occurred_at', '<=', $filters['date_to'].' 23:59:59');
        }
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($filters['sort'] ?? 'occurred_at', $direction)->orderBy('id', $direction);

        return InventoryMovementResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function store(InventoryMovementRequest $request, InventoryMovementService $inventory): JsonResponse
    {
        $result = $inventory->confirm($request->user(), $request->validated());

        return (new InventoryMovementResource($result['movement']->load($this->relations())))
            ->additional(['idempotent_replay' => $result['replayed']])->response()->setStatusCode($result['replayed'] ? 200 : 201);
    }

    public function show(InventoryMovement $movement): InventoryMovementResource
    {
        Gate::authorize('view', $movement);

        return new InventoryMovementResource($movement->load($this->relations()));
    }

    public function dashboard(OperationsIndexRequest $request, InventoryStockService $stock): JsonResponse
    {
        Gate::authorize(Permission::MaterialsView->value);
        $filters = $request->validated();
        $query = $stock->query();
        $statusSql = InventoryStockStatus::sql();
        $groups = (clone $query)->select([])->selectRaw($statusSql.' AS stock_status, COUNT(*) AS total')->groupByRaw($statusSql)->pluck('total', 'stock_status');
        $counts = [];
        foreach (InventoryStockStatus::cases() as $status) {
            $counts[$status->value] = (int) ($groups[$status->value] ?? 0);
        }
        $alerts = $query->whereRaw('('.$statusSql.") <> 'ok'")->orderByRaw('CASE ('.$statusSql.") WHEN 'critical' THEN 0 WHEN 'reorder' THEN 1 WHEN 'overstock' THEN 2 ELSE 3 END")
            ->orderBy('materials.code')->orderBy('materials.id')->paginate($filters['per_page'] ?? 20)->appends($filters);

        return response()->json(['indicators' => ['total_components' => array_sum($counts), ...$counts],
            'alerts' => MaterialResource::collection($alerts)->response()->getData(true)]);
    }
}
