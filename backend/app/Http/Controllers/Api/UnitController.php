<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Units\StoreUnitRequest;
use App\Http\Requests\Units\UnitIndexRequest;
use App\Http\Requests\Units\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    public function index(UnitIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Unit::query()->with('registeredBy');
        if (isset($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $search) use ($term): void {
                $search->where('vin', 'like', $term)->orWhere('model', 'like', $term)->orWhere('current_location', 'like', $term);
            });
        }
        foreach (['vin' => 'vin', 'model' => 'model', 'status' => 'status', 'location' => 'current_location'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($filters['sort'] ?? 'created_at', $direction)->orderBy('id', $direction);

        return UnitResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = new Unit($request->validated());
        $unit->registeredBy()->associate($request->user());
        try {
            $unit->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['vin' => 'Este VIN ya está registrado.']);
        }

        return (new UnitResource($unit->load('registeredBy')))->response()->setStatusCode(201);
    }

    public function show(Unit $unit): UnitResource
    {
        Gate::authorize('view', $unit);

        return new UnitResource($unit->load('registeredBy'));
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $unit->update($request->validated());

        return new UnitResource($unit->load('registeredBy'));
    }
}
