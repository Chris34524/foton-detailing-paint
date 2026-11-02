<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\DefectTypeIndexRequest;
use App\Http\Requests\Diagnosis\StoreDefectTypeRequest;
use App\Http\Requests\Diagnosis\UpdateDefectTypeRequest;
use App\Http\Resources\DefectTypeResource;
use App\Models\DefectType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class DefectTypeController extends Controller
{
    public function index(DefectTypeIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = DefectType::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return DefectTypeResource::collection($query->orderBy('name')->orderBy('id')
            ->paginate($filters['per_page'] ?? 100)->appends($filters));
    }

    private function save(DefectType $type, array $data): DefectType
    {
        try {
            $type->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Este tipo de defecto ya está registrado.']);
        }

        return $type;
    }

    public function store(StoreDefectTypeRequest $request): JsonResponse
    {
        return (new DefectTypeResource($this->save(new DefectType, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateDefectTypeRequest $request, DefectType $defectType): DefectTypeResource
    {
        return new DefectTypeResource($this->save($defectType, $request->validated()));
    }
}
