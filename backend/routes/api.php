<?php

use App\Http\Controllers\Api\DefectController;
use App\Http\Controllers\Api\DefectEvidenceController;
use App\Http\Controllers\Api\DefectTypeController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\DiagnosisMetaController;
use App\Http\Controllers\Api\InspectionChecklistController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MaterialConsumptionController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\PaintFormulaController;
use App\Http\Controllers\Api\ProcessController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UnitMetaController;
use App\Http\Controllers\Api\UnitVinLookupController;
use App\Http\Controllers\Api\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'foton-detailing-paint-api',
    ]);
});

Route::middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::post('/diagnoses/{diagnosis}/work-order', [WorkOrderController::class, 'generate'])->whereNumber('diagnosis')->name('diagnoses.work-order.store');
    Route::prefix('work-orders')->name('work-orders.')->group(function (): void {
        Route::get('/', [WorkOrderController::class, 'index'])->name('index');
        Route::get('/queue', [WorkOrderController::class, 'queue'])->name('queue');
        Route::get('/meta', [WorkOrderController::class, 'meta'])->name('meta');
        Route::get('/operators', [WorkOrderController::class, 'operators'])->name('operators');
        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->whereNumber('workOrder')->name('show');
        Route::patch('/{workOrder}', [WorkOrderController::class, 'update'])->whereNumber('workOrder')->name('update');
        Route::patch('/{workOrder}/assignment', [WorkOrderController::class, 'assignment'])->whereNumber('workOrder')->name('assignment');
        Route::patch('/{workOrder}/status', [WorkOrderController::class, 'status'])->whereNumber('workOrder')->name('status');
    });
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

Route::middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/process-sequence', [ProcessController::class, 'sequence'])->name('process-sequence.show');
    Route::put('/process-sequence', [ProcessController::class, 'replaceSequence'])->name('process-sequence.update');
    Route::get('/processes', [ProcessController::class, 'index'])->name('processes.index');
    Route::post('/processes', [ProcessController::class, 'store'])->name('processes.store');
    Route::get('/processes/{process}', [ProcessController::class, 'show'])->whereNumber('process')->name('processes.show');
    Route::patch('/processes/{process}', [ProcessController::class, 'update'])->whereNumber('process')->name('processes.update');
    Route::get('/work-orders/{workOrder}/process-flow', [ProcessController::class, 'flow'])->whereNumber('workOrder')->name('work-orders.process-flow.show');
    Route::post('/work-orders/{workOrder}/process-flow/initialize', [ProcessController::class, 'initialize'])->whereNumber('workOrder')->name('work-orders.process-flow.initialize');
    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::get('/materials/{material}', [MaterialController::class, 'show'])->whereNumber('material')->name('materials.show');
    Route::patch('/materials/{material}', [MaterialController::class, 'update'])->whereNumber('material')->name('materials.update');
    Route::get('/paint-formulas', [PaintFormulaController::class, 'index'])->name('paint-formulas.index');
    Route::post('/paint-formulas', [PaintFormulaController::class, 'store'])->name('paint-formulas.store');
    Route::get('/paint-formulas/{formula}', [PaintFormulaController::class, 'show'])->whereNumber('formula')->name('paint-formulas.show');
    Route::get('/paint-formulas/{formula}/versions', [PaintFormulaController::class, 'versions'])->whereNumber('formula')->name('paint-formulas.versions');
    Route::post('/paint-formulas/{formula}/versions', [PaintFormulaController::class, 'revise'])->whereNumber('formula')->name('paint-formulas.revise');
    Route::post('/paint-formulas/{formula}/preview', [PaintFormulaController::class, 'preview'])->whereNumber('formula')->name('paint-formulas.preview');
    Route::post('/paint-formulas/{formula}/prepare', [PaintFormulaController::class, 'prepare'])->whereNumber('formula')->name('paint-formulas.prepare');
    Route::get('/paint-preparations', [PaintFormulaController::class, 'preparations'])->name('paint-preparations.index');
    Route::get('/material-consumptions', [MaterialConsumptionController::class, 'index'])->name('material-consumptions.index');
    Route::get('/work-orders/{workOrder}/material-consumptions', [MaterialConsumptionController::class, 'index'])->whereNumber('workOrder')->name('work-orders.material-consumptions.index');
    Route::post('/work-orders/{workOrder}/material-consumptions', [MaterialConsumptionController::class, 'store'])->whereNumber('workOrder')->name('work-orders.material-consumptions.store');
    Route::get('/integrations/warehouse/v1/materials', [MaterialController::class, 'index'])->name('warehouse.materials.index');
    Route::get('/integrations/warehouse/v1/material-consumptions', [MaterialConsumptionController::class, 'index'])->name('warehouse.consumptions.index');
    Route::get('/inspection/checklists', [InspectionChecklistController::class, 'index'])->name('checklists.index');
    Route::post('/inspection/checklists', [InspectionChecklistController::class, 'store'])->name('checklists.store');
    Route::get('/inspection/checklists/{checklist}', [InspectionChecklistController::class, 'show'])->whereNumber('checklist')->name('checklists.show');
    Route::patch('/inspection/checklists/{checklist}', [InspectionChecklistController::class, 'update'])->whereNumber('checklist')->name('checklists.update');
    Route::post('/inspection/checklists/{checklist}/items', [InspectionChecklistController::class, 'storeItem'])->whereNumber('checklist')->name('checklists.items.store');
    Route::patch('/inspection/checklists/{checklist}/items/{item}', [InspectionChecklistController::class, 'updateItem'])->whereNumber(['checklist', 'item'])->name('checklists.items.update');
    Route::put('/inspection/checklists/{checklist}/item-order', [InspectionChecklistController::class, 'reorder'])->whereNumber('checklist')->name('checklists.items.order');
    Route::get('/inspection/work-orders', [InspectionController::class, 'eligibleOrders'])->name('inspection.work-orders');
    Route::get('/inspections', [InspectionController::class, 'index'])->name('inspections.index');
    Route::get('/work-orders/{workOrder}/inspections', [InspectionController::class, 'index'])->whereNumber('workOrder')->name('work-orders.inspections.index');
    Route::post('/work-orders/{workOrder}/inspections', [InspectionController::class, 'store'])->whereNumber('workOrder')->name('work-orders.inspections.store');
    Route::get('/inspections/{inspection}', [InspectionController::class, 'show'])->whereNumber('inspection')->name('inspections.show');
    Route::patch('/inspections/{inspection}', [InspectionController::class, 'update'])->whereNumber('inspection')->name('inspections.update');
    Route::post('/inspections/{inspection}/complete', [InspectionController::class, 'complete'])->whereNumber('inspection')->name('inspections.complete');
    Route::post('/inspections/{inspection}/release', [InspectionController::class, 'release'])->whereNumber('inspection')->name('inspections.release');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/materials/meta', [InventoryController::class, 'meta'])->name('materials.meta');
    Route::get('/materials/inventory/dashboard', [InventoryController::class, 'dashboard'])->name('inventory.dashboard');
    Route::get('/materials/{material}/stock', [InventoryController::class, 'stock'])->whereNumber('material')->name('inventory.stock');
    Route::get('/materials/{material}/quantity-preview', [InventoryController::class, 'preview'])->whereNumber('material')->name('inventory.quantity-preview');
    Route::get('/materials/{material}/movements', [InventoryController::class, 'index'])->whereNumber('material')->name('inventory.material-movements');
    Route::get('/inventory-movements', [InventoryController::class, 'index'])->name('inventory-movements.index');
    Route::post('/inventory-movements', [InventoryController::class, 'store'])->name('inventory-movements.store');
    Route::get('/inventory-movements/{movement}', [InventoryController::class, 'show'])->whereNumber('movement')->name('inventory-movements.show');
});
