<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\DiagnosisIndexRequest;
use App\Http\Requests\Diagnosis\StoreDiagnosisRequest;
use App\Http\Requests\Diagnosis\UpdateDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DiagnosisController extends Controller
{
    private function detail(Diagnosis $diagnosis): Diagnosis
    {
        return $diagnosis->load(['unit', 'diagnosedBy', 'defects.defectType', 'defects.createdBy',
            'defects.evidences.uploadedBy', 'defects.evidences.defect'])->loadCount('defects');
    }

    public function index(DiagnosisIndexRequest $request, ?Unit $unit = null): AnonymousResourceCollection
    {
        if ($unit !== null) {
            Gate::authorize('view', $unit);
        }
        $filters = $request->validated();
        $query = Diagnosis::query()->with(['unit', 'diagnosedBy'])->withCount('defects');
        if ($unit !== null) {
            $query->where('unit_id', $unit->id);
        }
        if (isset($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }
        if (isset($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $search) => $search->where('notes', 'like', $term)
                ->orWhereHas('unit', fn (Builder $units) => $units->where('vin', 'like', $term)->orWhere('model', 'like', $term)));
        }
        $direction = $filters['direction'] ?? 'desc';
        $sort = $filters['sort'] ?? 'created_at';
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $query->orderBy('id', $direction);
        }

        return DiagnosisResource::collection($query->paginate($filters['per_page'] ?? 20)->appends($filters));
    }

    public function store(StoreDiagnosisRequest $request, Unit $unit): JsonResponse
    {
        $diagnosis = new Diagnosis($request->validated());
        $diagnosis->unit()->associate($unit);
        $diagnosis->diagnosedBy()->associate($request->user());
        $diagnosis->save();

        return (new DiagnosisResource($this->detail($diagnosis)))->response()->setStatusCode(201);
    }

    public function show(Diagnosis $diagnosis): DiagnosisResource
    {
        Gate::authorize('view', $diagnosis);

        return new DiagnosisResource($this->detail($diagnosis));
    }

    public function update(UpdateDiagnosisRequest $request, Diagnosis $diagnosis): DiagnosisResource
    {
        $diagnosis->update($request->validated());

        return new DiagnosisResource($this->detail($diagnosis));
    }
}
