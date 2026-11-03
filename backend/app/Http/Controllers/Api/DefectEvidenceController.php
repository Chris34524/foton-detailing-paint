<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\StoreEvidenceRequest;
use App\Http\Resources\DefectEvidenceResource;
use App\Models\Defect;
use App\Models\DefectEvidence;
use App\Models\Diagnosis;
use App\Services\DefectEvidenceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DefectEvidenceController extends Controller
{
    public function store(StoreEvidenceRequest $request, Diagnosis $diagnosis, Defect $defect, DefectEvidenceStorage $storage): JsonResponse
    {
        try {
            $evidences = $storage->store($defect, $request->user(), array_values($request->file('files')));
        } catch (Throwable $error) {
            report($error);

            return response()->json(['message' => 'No fue posible guardar las evidencias. Intenta de nuevo.'], 503);
        }

        return DefectEvidenceResource::collection($evidences)->response()->setStatusCode(201);
    }

    public function show(Diagnosis $diagnosis, Defect $defect, DefectEvidence $evidence, DefectEvidenceStorage $storage): StreamedResponse|JsonResponse
    {
        Gate::authorize('viewEvidence', $diagnosis);
        try {
            $disk = $storage->disk($evidence->disk);
            $exists = $disk->exists($evidence->path);
        } catch (Throwable $error) {
            report($error);

            return response()->json(['message' => 'No fue posible abrir la evidencia. Intenta de nuevo.'], 503);
        }
        abort_unless($exists, 404);

        return $disk->response($evidence->path, basename($evidence->path), [
            'Content-Type' => $evidence->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }
}
