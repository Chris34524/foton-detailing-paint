<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\StoreDefectRequest;
use App\Http\Requests\Diagnosis\UpdateDefectRequest;
use App\Http\Resources\DefectResource;
use App\Models\Defect;
use App\Models\Diagnosis;
use Illuminate\Http\JsonResponse;

class DefectController extends Controller
{
    private function detail(Defect $defect): Defect
    {
        return $defect->load(['defectType', 'createdBy', 'evidences.uploadedBy', 'evidences.defect']);
    }

    public function store(StoreDefectRequest $request, Diagnosis $diagnosis): JsonResponse
    {
        $defect = new Defect($request->validated());
        $defect->diagnosis()->associate($diagnosis);
        $defect->createdBy()->associate($request->user());
        $defect->save();

        return (new DefectResource($this->detail($defect)))->response()->setStatusCode(201);
    }

    public function update(UpdateDefectRequest $request, Diagnosis $diagnosis, Defect $defect): DefectResource
    {
        $defect->update($request->validated());

        return new DefectResource($this->detail($defect));
    }
}
