<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\InspectionCompleteRequest;
use App\Http\Requests\Operations\InspectionReleaseRequest;
use App\Http\Requests\Operations\InspectionRequest;
use App\Http\Requests\Operations\OperationsIndexRequest;
use App\Http\Resources\InspectionResource;
use App\Http\Resources\UnitReleaseResource;
use App\Models\Inspection;
use App\Models\WorkOrder;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InspectionController extends Controller
{
    private function relations(): array
    {
        return ['workOrder.diagnosis.unit', 'inspectedBy', 'rework.createdBy', 'release.releasedBy'];
    }

    private function detail(Inspection $inspection): InspectionResource
    {
        return new InspectionResource($inspection->load([...$this->relations(), 'items']));
    }

    public function index(OperationsIndexRequest $request, ?WorkOrder $workOrder = null): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Inspection::class);
        $filters = $request->filters(['id', 'created_at', 'updated_at']);
        $query = Inspection::query()->with($this->relations());
        if ($workOrder !== null) {
            $query->where('work_order_id', $workOrder->id);
        }
        if (isset($filters['work_order_id'])) {
            $query->where('work_order_id', $filters['work_order_id']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('notes', 'like', $term)->orWhere('checklist_name_snapshot', 'like', $term)
                ->orWhereHas('workOrder.diagnosis.unit', fn ($units) => $units->where('vin', 'like', $term)->orWhere('model', 'like', $term)));
        }

        return InspectionResource::collection($query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->orderByDesc('id')->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function eligibleOrders(OperationsIndexRequest $request): JsonResponse
    {
        Gate::authorize('create', Inspection::class);
        $filters = $request->filters(['id', 'created_at', 'updated_at']);
        $query = WorkOrder::query()->where('status', WorkOrderStatus::Completed)->with('diagnosis.unit');
        if (isset($filters['work_order_id'])) {
            $query->whereKey($filters['work_order_id']);
        }
        if (isset($filters['search'])) {
            $query->whereHas('diagnosis.unit', fn ($q) => $q->where('vin', 'like', '%'.$filters['search'].'%')->orWhere('model', 'like', '%'.$filters['search'].'%'));
        }
        $page = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 20)->appends($filters);
        $page->through(fn (WorkOrder $order): array => ['id' => $order->id, 'vin' => $order->diagnosis->unit->vin, 'model' => $order->diagnosis->unit->model]);

        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]]);
    }

    public function store(InspectionRequest $request, WorkOrder $workOrder, InspectionService $service): JsonResponse
    {
        return $this->detail($service->create($workOrder, $request->user(), $request->validated()))->response()->setStatusCode(201);
    }

    public function show(Inspection $inspection): InspectionResource
    {
        Gate::authorize('view', $inspection);

        return $this->detail($inspection);
    }

    public function update(InspectionRequest $request, Inspection $inspection, InspectionService $service): InspectionResource
    {
        return $this->detail($service->update($inspection, $request->validated()));
    }

    public function complete(InspectionCompleteRequest $request, Inspection $inspection, InspectionService $service): InspectionResource
    {
        return $this->detail($service->complete($inspection, $request->user(), $request->validated('rework_cause')));
    }

    public function release(InspectionReleaseRequest $request, Inspection $inspection, InspectionService $service): JsonResponse
    {
        return (new UnitReleaseResource($service->release($inspection, $request->user(), $request->validated('notes'))->load('releasedBy')))->response()->setStatusCode(201);
    }
}
