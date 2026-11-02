<?php

namespace App\Http\Controllers\Api;

use App\Enums\DefectPriority;
use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DiagnosisMetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', Diagnosis::class);

        return response()->json(['priorities' => array_map(
            fn (DefectPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()],
            DefectPriority::cases(),
        )]);
    }
}
