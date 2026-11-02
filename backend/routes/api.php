<?php

use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UnitMetaController;
use App\Http\Controllers\Api\UnitVinLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'foton-detailing-paint-api',
    ]);
});

Route::middleware('auth:sanctum')->prefix('units')->name('units.')->group(function (): void {
    Route::get('/', [UnitController::class, 'index'])->name('index');
    Route::post('/', [UnitController::class, 'store'])->name('store');
    Route::get('/meta', UnitMetaController::class)->name('meta');
    Route::get('/by-vin/{vin}', UnitVinLookupController::class)->name('by-vin');
    Route::get('/{unit}', [UnitController::class, 'show'])->whereNumber('unit')->name('show');
    Route::patch('/{unit}', [UnitController::class, 'update'])->whereNumber('unit')->name('update');
});
