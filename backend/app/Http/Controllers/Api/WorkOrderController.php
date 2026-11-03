<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\AssignWorkOrderRequest;
use App\Http\Requests\WorkOrder\ChangeWorkOrderStatusRequest;
use App\Http\Requests\WorkOrder\GenerateWorkOrderRequest;
use App\Http\Requests\WorkOrder\OperatorIndexRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Http\Requests\WorkOrder\WorkOrderIndexRequest;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\Diagnosis;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkOrderController extends Controller
{
    private function detail(WorkOrder $order): WorkOrderResource
    {
        $order->load(['diagnosis.unit', 'diagnosis.diagnosedBy', 'diagnosis.defects.defectType',
            'diagnosis.defects.createdBy', 'diagnosis.defects.evidences.uploadedBy',
            'diagnosis.defects.evidences.defect', 'assignedTo', 'assignedBy', 'createdBy']);
        $order->diagnosis->loadCount('defects');

        return new WorkOrderResource($order);
    }

    private function listing(WorkOrderIndexRequest $request, bool $queue): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = WorkOrder::query()->with(['diagnosis' => fn (BelongsTo $diagnoses) => $diagnoses->with('unit')->withCount('defects'),
            'assignedTo', 'assignedBy', 'createdBy']);
        if ($queue) {
            $query->whereNotIn('status', [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled]);
        }
        foreach (['status', 'priority', 'assigned_to'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['assignment'])) {
            $filters['assignment'] === 'assigned' ? $query->whereNotNull('assigned_to') : $query->whereNull('assigned_to');
        }
        if (isset($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $search) => $search->where('notes', 'like', $term)
                ->orWhereHas('diagnosis', fn (Builder $diagnoses) => $diagnoses->where('notes', 'like', $term)
                    ->orWhereHas('unit', fn (Builder $units) => $units->where('vin', 'like', $term)->orWhere('model', 'like', $term))));
        }
        if ($queue) {
            WorkOrderPriority::order($query, 'desc');
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $sort = $filters['sort'] ?? 'created_at';
            $direction = $filters['direction'] ?? 'desc';
            if ($sort === 'priority') {
                WorkOrderPriority::order($query, $direction);
            } else {
                $query->orderBy($sort, $direction);
            }
            $query->orderBy('id', $direction);
        }

        return WorkOrderResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function index(WorkOrderIndexRequest $request): AnonymousResourceCollection
    {
        return $this->listing($request, false);
    }

    public function queue(WorkOrderIndexRequest $request): AnonymousResourceCollection
    {
        return $this->listing($request, true);
    }

    public function meta(): JsonResponse
    {
        Gate::authorize('viewAny', WorkOrder::class);

        return response()->json([
            'statuses' => array_map(fn (WorkOrderStatus $status): array => ['value' => $status->value, 'label' => $status->label(), 'is_terminal' => $status->isTerminal()], WorkOrderStatus::cases()),
            'priorities' => array_map(fn (WorkOrderPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label(), 'rank' => $priority->rank()], WorkOrderPriority::cases()),
        ]);
    }

    public function operators(OperatorIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = User::role(Role::Operator->value)->select(['id', 'name']);
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return OperatorResource::collection($query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function show(WorkOrder $workOrder): WorkOrderResource
    {
        Gate::authorize('view', $workOrder);

        return $this->detail($workOrder);
    }

    public function generate(GenerateWorkOrderRequest $request, Diagnosis $diagnosis, WorkOrderService $service): JsonResponse
    {
        return $this->detail($service->generate($diagnosis, $request->user(), $request->validated('notes')))->response()->setStatusCode(201);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder, WorkOrderService $service): WorkOrderResource
    {
        return $this->detail($service->update($workOrder, $request->validated()));
    }

    public function assignment(AssignWorkOrderRequest $request, WorkOrder $workOrder, WorkOrderService $service): WorkOrderResource
    {
        $id = $request->validated('assigned_to');

        return $this->detail($service->assign($workOrder, $request->user(), $id === null ? null : (int) $id));
    }

    public function status(ChangeWorkOrderStatusRequest $request, WorkOrder $workOrder, WorkOrderService $service): WorkOrderResource
    {
        return $this->detail($service->changeStatus($workOrder, WorkOrderStatus::from($request->validated('status'))));
    }
}
