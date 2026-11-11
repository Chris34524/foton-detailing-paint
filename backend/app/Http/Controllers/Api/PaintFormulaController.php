<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\PaintFormulaRequest;
use App\Http\Requests\Operations\PaintPreparationRequest;
use App\Http\Requests\Operations\PaintPreviewRequest;
use App\Http\Resources\PaintFormulaResource;
use App\Http\Resources\PaintPreparationResource;
use App\Models\PaintFormula;
use App\Models\PaintPreparation;
use App\Services\PaintFormulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaintFormulaController extends Controller
{
    private function formulaRelations(): array
    {
        return ['product.inventoryBalance', 'components.material.inventoryBalance'];
    }

    private function preparationRelations(): array
    {
        return ['formula', 'product', 'outputMovement', 'preparedBy', 'components.movement'];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(Permission::MaterialsView->value);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'product_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = PaintFormula::query()->with($this->formulaRelations())->where('is_current', true);
        if (isset($filters['product_id'])) {
            $query->where('product_material_id', $filters['product_id']);
        }
        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $term = '%'.trim($filters['search']).'%';
            $query->where(fn ($q) => $q->where('vehicle_model', 'like', $term)
                ->orWhere('color_name', 'like', $term)->orWhere('color_code', 'like', $term));
        }

        return PaintFormulaResource::collection($query->orderByDesc('id')->paginate($filters['per_page'] ?? 20));
    }

    public function show(PaintFormula $formula): PaintFormulaResource
    {
        Gate::authorize(Permission::MaterialsView->value);

        return new PaintFormulaResource($formula->load($this->formulaRelations()));
    }

    public function versions(PaintFormula $formula): AnonymousResourceCollection
    {
        Gate::authorize(Permission::MaterialsView->value);

        return PaintFormulaResource::collection(PaintFormula::query()
            ->where('product_material_id', $formula->product_material_id)
            ->with($this->formulaRelations())->orderByDesc('version')->get());
    }

    public function store(PaintFormulaRequest $request, PaintFormulationService $service): JsonResponse
    {
        $formula = $service->create($request->user(), $request->validated());

        return (new PaintFormulaResource($formula))->response()->setStatusCode(201);
    }

    public function revise(PaintFormulaRequest $request, PaintFormula $formula, PaintFormulationService $service): JsonResponse
    {
        $revision = $service->revise($formula, $request->user(), $request->validated());

        return (new PaintFormulaResource($revision))->response()->setStatusCode(201);
    }

    public function preview(PaintPreviewRequest $request, PaintFormula $formula, PaintFormulationService $service): JsonResponse
    {
        return response()->json($service->preview($formula, (string) $request->validated('quantity_grams')));
    }

    public function prepare(PaintPreparationRequest $request, PaintFormula $formula, PaintFormulationService $service): JsonResponse
    {
        $result = $service->prepare($formula, $request->user(), $request->validated());
        $preparation = $result['preparation']->load($this->preparationRelations());

        return (new PaintPreparationResource($preparation))->additional(['idempotent_replay' => $result['replayed']])
            ->response()->setStatusCode($result['replayed'] ? 200 : 201);
    }

    public function preparations(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(Permission::MaterialsView->value);
        $filters = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return PaintPreparationResource::collection(PaintPreparation::query()->with($this->preparationRelations())
            ->orderByDesc('id')->paginate($filters['per_page'] ?? 20));
    }
}
