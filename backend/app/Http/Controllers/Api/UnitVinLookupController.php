<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Rules\Vin;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UnitVinLookupController extends Controller
{
    public function __invoke(string $vin): UnitResource
    {
        Gate::authorize('viewAny', Unit::class);
        $data = Validator::make(['vin' => Vin::normalize($vin)], ['vin' => ['required', new Vin]])->validate();
        $unit = Unit::query()->with('registeredBy')->where('vin', $data['vin'])->firstOrFail();

        return new UnitResource($unit);
    }
}
