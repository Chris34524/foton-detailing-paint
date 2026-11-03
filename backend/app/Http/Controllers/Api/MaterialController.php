<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\CatalogRequest;
use App\Http\Requests\Operations\OperationsIndexRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class MaterialController extends Controller
{
    public function index(OperationsIndexRequest $request): AnonymousResourceCollection
    {
        $integration = $request->routeIs('warehouse.*');
        $integration ? Gate::authorize(Permission::MaterialsManage->value) : Gate::authorize('viewAny', Material::class);
        $filters = $request->filters(['id', 'code', 'name', 'created_at', 'updated_at']);
        $query = Material::query()->with('createdBy');
        if (isset($filters['search'])) {
            $query->where(fn ($q) => $q->where('code', 'like', '%'.$filters['search'].'%')->orWhere('name', 'like', '%'.$filters['search'].'%'));
        }
        foreach (['active' => 'is_active', 'unit' => 'unit'] as $input => $column) {
            if (isset($filters[$input])) {
                $query->where($column, $filters[$input]);
            }
        }
        if (isset($filters['updated_since'])) {
            $query->where('updated_at', '>=', Carbon::parse($filters['updated_since'])->utc());
        }
        $query->orderBy($filters['sort'] ?? 'id', $filters['direction'] ?? 'asc')->orderBy('id');

        return MaterialResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters))->additional($integration ? ['contract_version' => '1'] : []);
    }

    public function store(CatalogRequest $request): JsonResponse
    {
        $record = new Material($request->validated());
        $record->created_by = $request->user()->id;
        $record->save();

        return (new MaterialResource($record->load('createdBy')))->response()->setStatusCode(201);
    }

    public function show(Material $material): MaterialResource
    {
        Gate::authorize('view', $material);

        return new MaterialResource($material->load('createdBy'));
    }

    public function update(CatalogRequest $request, Material $material): MaterialResource
    {
        $material->update($request->validated());

        return new MaterialResource($material->load('createdBy'));
    }
}
