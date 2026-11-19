<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\CatalogRequest;
use App\Http\Requests\Operations\OperationsIndexRequest;
use App\Http\Requests\Operations\ProcessSequenceRequest;
use App\Http\Resources\ProcessResource;
use App\Http\Resources\ProcessSequenceResource;
use App\Http\Resources\WorkOrderProcessStepResource;
use App\Models\Process;
use App\Models\ProcessSequenceStep;
use App\Models\WorkOrder;
use App\Services\ProcessFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProcessController extends Controller
{
    public function index(OperationsIndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Process::class);
        $filters = $request->filters(['id', 'code', 'name', 'created_at', 'updated_at']);
        $query = Process::query()->with('createdBy');
        if (isset($filters['search'])) {
            $query->where(fn ($q) => $q->where('code', 'like', '%'.$filters['search'].'%')->orWhere('name', 'like', '%'.$filters['search'].'%'));
        }
        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }
        $query->orderBy($filters['sort'] ?? 'code', $filters['direction'] ?? 'asc')->orderBy('id');

        return ProcessResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function store(CatalogRequest $request): JsonResponse
    {
        $record = new Process($request->validated());
        $record->created_by = $request->user()->id;
        $record->save();

        return (new ProcessResource($record->load('createdBy')))->response()->setStatusCode(201);
    }

    public function show(Process $process): ProcessResource
    {
        Gate::authorize('view', $process);

        return new ProcessResource($process->load('createdBy'));
    }

    public function update(CatalogRequest $request, Process $process, ProcessFlowService $service): ProcessResource
    {
        return new ProcessResource($service->updateProcess($process, $request->validated())->load('createdBy'));
    }

    public function sequence(): AnonymousResourceCollection
    {
        Gate::authorize(Permission::ProcessesView->value);

        return ProcessSequenceResource::collection(ProcessSequenceStep::query()->with('process')->orderBy('position')->get());
    }

    public function replaceSequence(ProcessSequenceRequest $request, ProcessFlowService $service): AnonymousResourceCollection
    {
        $service->replace($request->validated('process_ids'));

        return ProcessSequenceResource::collection(ProcessSequenceStep::query()->with('process')->orderBy('position')->get());
    }

    public function flow(WorkOrder $workOrder): JsonResponse
    {
        Gate::authorize('view', $workOrder);

        return $this->flowResponse($workOrder);
    }

    private function flowResponse(WorkOrder $order, int $status = 200): JsonResponse
    {
        $steps = $order->processSteps()->get();
        $current = $steps->firstWhere('id', $order->current_process_step_id);
        $next = $current === null ? null : $steps->first(fn ($step) => $step->position > $current->position);

        return response()->json(['initialized' => $steps->isNotEmpty(), 'current' => $current === null ? null : new WorkOrderProcessStepResource($current),
            'next' => $next === null ? null : new WorkOrderProcessStepResource($next), 'steps' => WorkOrderProcessStepResource::collection($steps)], $status);
    }

    public function initialize(WorkOrder $workOrder, ProcessFlowService $service): JsonResponse
    {
        Gate::authorize(Permission::ProcessesManage->value);

        return $this->flowResponse($service->initialize($workOrder), 201);
    }
}
