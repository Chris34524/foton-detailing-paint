<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStockStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\CatalogRequest;
use App\Http\Requests\Operations\OperationsIndexRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\InventoryStockService;
use App\Services\MaterialCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class MaterialController extends Controller
{
    public function index(OperationsIndexRequest $request, InventoryStockService $stock): AnonymousResourceCollection
    {
        $integration = $request->routeIs('warehouse.*');
        $integration ? Gate::authorize(Permission::MaterialsManage->value) : Gate::authorize('viewAny', Material::class);
        $filters = $request->filters(['id', 'code', 'name', 'created_at', 'updated_at']);
        $query = $stock->query();
        if (isset($filters['search'])) {
            $query->where(fn ($q) => $q->where('materials.code', 'like', '%'.$filters['search'].'%')->orWhere('materials.name', 'like', '%'.$filters['search'].'%'));
        }
        foreach (['active' => 'materials.is_active', 'unit' => 'materials.unit'] as $input => $column) {
            if (isset($filters[$input])) {
                $query->where($column, $filters[$input]);
            }
        }
        if (isset($filters['stock_status'])) {
            $query->whereRaw('('.InventoryStockStatus::sql().') = ?', [$filters['stock_status']]);
        }
        if (isset($filters['updated_since'])) {
            $query->where('materials.updated_at', '>=', Carbon::parse($filters['updated_since'])->utc());
        }
        $query->orderBy('materials.'.($filters['sort'] ?? 'code'), $filters['direction'] ?? 'asc')->orderBy('materials.id');

        return MaterialResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters))->additional($integration ? ['contract_version' => '1'] : []);
    }

    public function store(CatalogRequest $request, MaterialCatalogService $catalog): JsonResponse
    {
        $material = $catalog->create($request->user(), $request->validated())->load(['createdBy', 'inventoryBalance'])->loadExists(['inventoryMovements', 'materialConsumptions']);

        return (new MaterialResource($material))->response()->setStatusCode(201);
    }

    public function show(Material $material): MaterialResource
    {
        Gate::authorize('view', $material);

        return new MaterialResource($material->load(['createdBy', 'inventoryBalance'])->loadExists(['inventoryMovements', 'materialConsumptions']));
    }

    public function update(CatalogRequest $request, Material $material, MaterialCatalogService $catalog): MaterialResource
    {
        return new MaterialResource($catalog->update($material, $request->validated())->load(['createdBy', 'inventoryBalance'])->loadExists(['inventoryMovements', 'materialConsumptions']));
    }
}
