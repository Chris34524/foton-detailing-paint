<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\MaterialConsumptionRequest;
use App\Http\Requests\Operations\OperationsIndexRequest;
use App\Http\Resources\MaterialConsumptionResource;
use App\Models\MaterialConsumption;
use App\Models\WorkOrder;
use App\Services\MaterialConsumptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class MaterialConsumptionController extends Controller
{
    private function relations(): array
    {
        return ['workOrder.diagnosis.unit', 'processStep', 'consumedBy', 'inventoryMovement.performedBy'];
    }

    public function index(OperationsIndexRequest $request, ?WorkOrder $workOrder = null): AnonymousResourceCollection
    {
        $integration = $request->routeIs('warehouse.*');
        $integration ? Gate::authorize(Permission::MaterialsManage->value) : Gate::authorize('viewAny', MaterialConsumption::class);
        $filters = $request->filters(['id', 'created_at', 'updated_at', 'consumed_at']);
        $query = MaterialConsumption::query()->with($this->relations());
        if ($workOrder !== null) {
            $query->where('work_order_id', $workOrder->id);
        }
        if (isset($filters['work_order_id'])) {
            $query->where('work_order_id', $filters['work_order_id']);
        }
        if (isset($filters['vin'])) {
            $query->whereHas('workOrder.diagnosis.unit', fn ($q) => $q->where('vin', $filters['vin']));
        }
        if (isset($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('material_name_snapshot', 'like', $term)->orWhere('material_code_snapshot', 'like', $term)
                ->orWhereHas('workOrder.diagnosis.unit', fn ($units) => $units->where('vin', 'like', $term)->orWhere('model', 'like', $term)));
        }
        if (isset($filters['updated_since'])) {
            $query->where('updated_at', '>=', Carbon::parse($filters['updated_since'])->utc());
        }
        $query->orderBy($filters['sort'] ?? 'consumed_at', $filters['direction'] ?? 'desc')->orderByDesc('id');

        return MaterialConsumptionResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters))->additional($integration ? ['contract_version' => '1'] : []);
    }

    public function store(MaterialConsumptionRequest $request, WorkOrder $workOrder, MaterialConsumptionService $service): JsonResponse
    {
        $result = $service->consume($workOrder, $request->user(), $request->validated());

        return (new MaterialConsumptionResource($result['consumption']->load($this->relations())))
            ->additional(['idempotent_replay' => $result['replayed']])->response()->setStatusCode($result['replayed'] ? 200 : 201);
    }
}
