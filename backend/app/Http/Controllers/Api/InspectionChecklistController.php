<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\CatalogRequest;
use App\Http\Requests\Operations\ChecklistItemRequest;
use App\Http\Requests\Operations\ChecklistOrderRequest;
use App\Http\Requests\Operations\OperationsIndexRequest;
use App\Http\Resources\InspectionChecklistItemResource;
use App\Http\Resources\InspectionChecklistResource;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InspectionChecklistController extends Controller
{
    public function index(OperationsIndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InspectionChecklist::class);
        $filters = $request->filters(['id', 'name', 'created_at', 'updated_at']);
        $query = InspectionChecklist::query()->with('createdBy')->withCount('items');
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        return InspectionChecklistResource::collection($query->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')->orderBy('id')->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function store(CatalogRequest $request): JsonResponse
    {
        $checklist = new InspectionChecklist($request->validated());
        $checklist->created_by = $request->user()->id;
        $checklist->save();

        return (new InspectionChecklistResource($checklist->load(['createdBy', 'items'])))->response()->setStatusCode(201);
    }

    public function show(InspectionChecklist $checklist): InspectionChecklistResource
    {
        Gate::authorize('view', $checklist);

        return new InspectionChecklistResource($checklist->load(['createdBy', 'items']));
    }

    public function update(CatalogRequest $request, InspectionChecklist $checklist): InspectionChecklistResource
    {
        DB::transaction(function () use ($checklist, $request): void {
            $current = InspectionChecklist::query()->lockForUpdate()->findOrFail($checklist->id);
            $current->update($request->validated());
        }, 3);

        return new InspectionChecklistResource($checklist->fresh()->load(['createdBy', 'items']));
    }

    public function storeItem(ChecklistItemRequest $request, InspectionChecklist $checklist): JsonResponse
    {
        $item = DB::transaction(function () use ($checklist, $request): InspectionChecklistItem {
            $current = InspectionChecklist::query()->lockForUpdate()->findOrFail($checklist->id);
            if ($current->items()->count() >= 1000) {
                throw ValidationException::withMessages(['items' => 'El checklist admite hasta 1000 criterios.']);
            }
            if ($current->items()->where('code', $request->validated('code'))->exists()) {
                throw ValidationException::withMessages(['code' => 'El código ya está registrado en este checklist.']);
            }
            $item = new InspectionChecklistItem($request->validated());
            $item->inspection_checklist_id = $current->id;
            $item->sort_order = ($current->items()->max('sort_order') ?? 0) + 1;
            $item->save();

            return $item;
        }, 3);

        return (new InspectionChecklistItemResource($item))->response()->setStatusCode(201);
    }

    public function updateItem(ChecklistItemRequest $request, InspectionChecklist $checklist, InspectionChecklistItem $item): InspectionChecklistItemResource
    {
        abort_unless($item->inspection_checklist_id === $checklist->id, 404);
        DB::transaction(function () use ($checklist, $item, $request): void {
            InspectionChecklist::query()->whereKey($checklist->id)->lockForUpdate()->firstOrFail();
            $item->update($request->validated());
        }, 3);

        return new InspectionChecklistItemResource($item->fresh());
    }

    public function reorder(ChecklistOrderRequest $request, InspectionChecklist $checklist): InspectionChecklistResource
    {
        DB::transaction(function () use ($checklist, $request): void {
            $current = InspectionChecklist::query()->lockForUpdate()->findOrFail($checklist->id);
            $items = $current->items()->get();
            $ids = $request->validated('item_ids');
            if (count($ids) !== $items->count() || array_diff($ids, $items->modelKeys()) !== []) {
                throw ValidationException::withMessages(['item_ids' => 'Incluye todos los criterios de este checklist, sin duplicados.']);
            }
            foreach ($items as $index => $item) {
                $item->sort_order = -($index + 1);
                $item->save();
            }
            foreach ($ids as $index => $id) {
                $item = $items->find($id);
                $item->sort_order = $index + 1;
                $item->save();
            }
        }, 3);

        return new InspectionChecklistResource($checklist->load(['createdBy', 'items']));
    }
}
