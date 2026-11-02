<?php

use App\Http\Controllers\Api\DefectController;
use App\Http\Controllers\Api\DefectEvidenceController;
use App\Http\Controllers\Api\DefectTypeController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\DiagnosisMetaController;
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

Route::middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/diagnosis/meta', DiagnosisMetaController::class)->name('diagnosis.meta');
    Route::get('/diagnoses', [DiagnosisController::class, 'index'])->name('diagnoses.index');
    Route::get('/units/{unit}/diagnoses', [DiagnosisController::class, 'index'])->whereNumber('unit')->name('units.diagnoses.index');
    Route::post('/units/{unit}/diagnoses', [DiagnosisController::class, 'store'])->whereNumber('unit')->name('units.diagnoses.store');
    Route::get('/defect-types', [DefectTypeController::class, 'index'])->name('defect-types.index');
    Route::post('/defect-types', [DefectTypeController::class, 'store'])->name('defect-types.store');
    Route::patch('/defect-types/{defectType}', [DefectTypeController::class, 'update'])->whereNumber('defectType')->name('defect-types.update');
    Route::prefix('diagnoses/{diagnosis}')->whereNumber('diagnosis')->name('diagnoses.')->group(function (): void {
        Route::get('/', [DiagnosisController::class, 'show'])->name('show');
        Route::patch('/', [DiagnosisController::class, 'update'])->name('update');
        Route::post('/defects', [DefectController::class, 'store'])->name('defects.store');
        Route::patch('/defects/{defect}', [DefectController::class, 'update'])->whereNumber('defect')->name('defects.update');
        Route::post('/defects/{defect}/evidences', [DefectEvidenceController::class, 'store'])->whereNumber('defect')->name('evidence.store');
        Route::get('/defects/{defect}/evidences/{evidence}', [DefectEvidenceController::class, 'show'])
            ->whereNumber(['defect', 'evidence'])->name('evidence.show');
    });
});

Route::middleware('auth:sanctum')->prefix('units')->name('units.')->group(function (): void {
    Route::get('/', [UnitController::class, 'index'])->name('index');
    Route::post('/', [UnitController::class, 'store'])->name('store');
    Route::get('/meta', UnitMetaController::class)->name('meta');
    Route::get('/by-vin/{vin}', UnitVinLookupController::class)->name('by-vin');
    Route::get('/{unit}', [UnitController::class, 'show'])->whereNumber('unit')->name('show');
    Route::patch('/{unit}', [UnitController::class, 'update'])->whereNumber('unit')->name('update');
});
