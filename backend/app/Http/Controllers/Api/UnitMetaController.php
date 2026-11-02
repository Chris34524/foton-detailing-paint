<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UnitMetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', Unit::class);

        return response()->json([
            'statuses' => array_map(
                fn (UnitStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                UnitStatus::cases(),
            ),
        ]);
    }
}
