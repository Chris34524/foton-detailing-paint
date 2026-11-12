<?php

namespace App\Services;

use App\Models\Material;
use App\Models\PaintFormula;
use App\Models\PaintPreparation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaintFormulationService
{
    public function __construct(
        private MaterialCatalogService $catalog,
        private MaterialQuantityConverter $converter,
        private InventoryMovementService $inventory,
    ) {}

    /** @return array{total:string,components:array} */
    private function ingredients(array $rows): array
    {
        $ids = array_column($rows, 'material_id');
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['components' => 'Cada materia prima solo puede aparecer una vez en la fórmula.']);
        }

        $materials = Material::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $components = [];
        $total = '0.00';
        foreach ($rows as $row) {
            $material = $materials->get((int) $row['material_id']);
            if ($material === null || ! $material->is_active || $material->kind !== 'raw_material'
                || (! in_array($material->unit, ['Kg', 'gr'], true)
                    && (! in_array($material->unit, ['ml', 'L'], true) || $material->density_g_per_ml === null))) {
                throw ValidationException::withMessages(['components' => 'Selecciona materias primas activas en gr/Kg, o en ml/L con densidad g/ml configurada.']);
            }
            $grams = bcadd((string) $row['grams'], '0', 2);
            $total = bcadd($total, $grams, 2);
            $components[] = [
                'material_id' => $material->id,
                'material_code_snapshot' => $material->code,
                'material_name_snapshot' => $material->name,
                'grams' => $grams,
            ];
        }
        if (bccomp($total, '999999999999.99', 2) > 0) {
            throw ValidationException::withMessages(['components' => 'El gramaje total de la fórmula es demasiado grande.']);
        }

        return ['total' => $total, 'components' => $components];
    }

    public function create(User $actor, array $input): PaintFormula
    {
        return DB::transaction(function () use ($actor, $input): PaintFormula {
            $model = mb_strtoupper(trim($input['vehicle_model']));
            $colorCode = mb_strtoupper(trim($input['color_code']));
            if (PaintFormula::query()->where('vehicle_model', $model)->where('color_code', $colorCode)->exists()) {
                throw ValidationException::withMessages(['color_code' => 'Ya existe una formulación para este modelo y código de color. Crea una nueva versión.']);
            }
            $recipe = $this->ingredients($input['components']);
            $color = trim($input['color_name']);
            $product = $this->catalog->create($actor, [
                'name' => mb_substr('Pintura '.$color.' · '.$model, 0, 160),
                'unit' => 'gr',
                'is_active' => true,
            ]);
            $product->kind = 'finished_paint';
            $product->save();

            $formula = PaintFormula::query()->create([
                'product_material_id' => $product->id,
                'vehicle_model' => $model,
                'color_name' => $color,
                'color_code' => $colorCode,
                'version' => 1,
                'total_grams' => $recipe['total'],
                'is_current' => true,
                'created_by' => $actor->id,
            ]);
            $formula->components()->createMany($recipe['components']);

            return $formula->load(['product.inventoryBalance', 'components.material.inventoryBalance']);
        }, 3);
    }

    public function revise(PaintFormula $formula, User $actor, array $input): PaintFormula
    {
        return DB::transaction(function () use ($formula, $actor, $input): PaintFormula {
            $product = Material::query()->lockForUpdate()->findOrFail($formula->product_material_id);
            $current = PaintFormula::query()->whereKey($formula->id)->lockForUpdate()->firstOrFail();
            if (! $current->is_current) {
                throw ValidationException::withMessages(['formula' => 'La fórmula ya tiene una versión más reciente.']);
            }
            if (mb_strtoupper(trim($input['vehicle_model'])) !== $current->vehicle_model
                || mb_strtoupper(trim($input['color_code'])) !== $current->color_code) {
                throw ValidationException::withMessages(['color_code' => 'Para cambiar el modelo o código de color, crea una fórmula nueva.']);
            }
            $recipe = $this->ingredients($input['components']);
            $current->is_current = false;
            $current->save();
            $color = trim($input['color_name']);
            $product->name = mb_substr('Pintura '.$color.' · '.$current->vehicle_model, 0, 160);
            $product->save();
            $revision = PaintFormula::query()->create([
                'product_material_id' => $product->id,
                'vehicle_model' => $current->vehicle_model,
                'color_name' => $color,
                'color_code' => $current->color_code,
                'version' => $current->version + 1,
                'total_grams' => $recipe['total'],
                'is_current' => true,
                'created_by' => $actor->id,
            ]);
            $revision->components()->createMany($recipe['components']);

            return $revision->load(['product.inventoryBalance', 'components.material.inventoryBalance']);
        }, 3);
    }

    /** @return array<string,mixed> */
    public function preview(PaintFormula $formula, string $requested): array
    {
        $quantity = $this->converter->amount($requested, true, 'quantity_grams');
        if (bccomp($quantity, '999999999999.99', 6) > 0) {
            throw ValidationException::withMessages(['quantity_grams' => 'La preparación supera el máximo de gramos.']);
        }
        $quantity = bcadd($quantity, '0', 2);
        $formula->loadMissing(['product.inventoryBalance', 'components.material.inventoryBalance']);
        $total = $formula->total_grams;
        $components = [];
        $sum = '0.00';

        foreach ($formula->components->sortBy('material_id') as $component) {
            $exact = bcdiv(bcmul($component->grams, $quantity, 4), $total, 8);
            $floor = bcadd($exact, '0', 2);
            $sum = bcadd($sum, $floor, 2);
            $components[] = [
                'material_id' => $component->material_id,
                'code' => $component->material_code_snapshot,
                'name' => $component->material_name_snapshot,
                'grams' => $floor,
                'fraction' => bcsub($exact, $floor, 8),
                'material' => $component->material,
            ];
        }

        // Largest-remainder apportionment preserves both 0.01 g precision and mass balance.
        $cents = (int) bcmul(bcsub($quantity, $sum, 2), '100', 0);
        $ranks = array_keys($components);
        usort($ranks, function (int $a, int $b) use ($components): int {
            $difference = bccomp($components[$b]['fraction'], $components[$a]['fraction'], 8);

            return $difference !== 0 ? $difference : $components[$a]['material_id'] <=> $components[$b]['material_id'];
        });
        for ($i = 0; $i < $cents; $i++) {
            $index = $ranks[$i % count($ranks)];
            $components[$index]['grams'] = bcadd($components[$index]['grams'], '0.01', 2);
        }

        $possible = $formula->is_current && $formula->product->is_active && $formula->product->kind === 'finished_paint';
        $result = [];
        foreach ($components as $component) {
            $material = $component['material'];
            if (bccomp($component['grams'], '0', 2) <= 0) {
                throw ValidationException::withMessages(['quantity_grams' => 'El lote es demasiado pequeño: al menos un pigmento quedaría por debajo de 0.01 g.']);
            }
            $needed = $this->converter->gramsForStock($component['grams'], $material);
            $available = $material->inventoryBalance?->quantity ?? '0.000000';
            $sufficient = bccomp($available, $needed, 6) >= 0;
            $possible = $possible && $material->is_active && $material->kind === 'raw_material' && $sufficient;
            $result[] = [
                'material_id' => $material->id,
                'code' => $component['code'],
                'name' => $component['name'],
                'grams' => $component['grams'],
                'required_quantity' => $needed,
                'available_quantity' => $available,
                'unit' => $material->unit,
                'density_g_per_ml' => $material->density_g_per_ml,
                'sufficient' => $sufficient,
            ];
        }

        $finishedStock = $formula->product->inventoryBalance?->quantity ?? '0.000000';
        $possible = $possible && bccomp(bcadd($finishedStock, $quantity, 6), MaterialQuantityConverter::MAX, 6) <= 0;

        return [
            'formula_id' => $formula->id,
            'version' => $formula->version,
            'quantity_grams' => $quantity,
            'total_ingredient_grams' => array_reduce($result, fn (string $sum, array $item): string => bcadd($sum, $item['grams'], 2), '0.00'),
            'components' => $result,
            'product' => ['id' => $formula->product->id, 'code' => $formula->product->code, 'name' => $formula->product->name,
                'stock_before' => $finishedStock, 'unit' => 'gr'],
            'can_confirm' => $possible,
        ];
    }

    public function prepare(PaintFormula $formula, User $actor, array $input): array
    {
        $key = mb_strtolower($input['idempotency_key']);
        $quantity = bcadd((string) $input['quantity_grams'], '0', 2);
        $fingerprint = hash('sha256', json_encode([
            $actor->id, $formula->id, $quantity, $input['area_destination'] ?? null, $input['notes'] ?? null,
        ], JSON_THROW_ON_ERROR));

        $replay = function () use ($key, $fingerprint): ?array {
            $existing = PaintPreparation::query()->where('idempotency_key', $key)->first();
            if ($existing === null) {
                return null;
            }
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['idempotency_key' => 'La clave ya se utilizó para una preparación diferente.']);
            }

            return ['preparation' => $existing, 'replayed' => true];
        };

        try {
            return DB::transaction(function () use ($formula, $actor, $input, $key, $fingerprint, $quantity, $replay): array {
                if (($existing = $replay()) !== null) {
                    return $existing;
                }
                Material::query()->whereKey($formula->product_material_id)->lockForUpdate()->firstOrFail();
                $current = PaintFormula::query()->whereKey($formula->id)->lockForUpdate()->firstOrFail();
                if (! $current->is_current) {
                    throw ValidationException::withMessages(['formula' => 'Solo puedes preparar la versión vigente de una pintura.']);
                }
                // Congela las densidades de materias primas mientras se calcula y confirma el lote.
                $componentIds = $current->components()->orderBy('material_id')->pluck('material_id');
                Material::query()->whereIn('id', $componentIds)->orderBy('id')->lockForUpdate()->get();
                $preview = $this->preview($current, $quantity);
                if (! $preview['can_confirm']) {
                    throw ValidationException::withMessages(['components' => 'No hay existencias suficientes o un material ya no está activo. Verifica el desglose.']);
                }
                $preparation = PaintPreparation::query()->create([
                    'paint_formula_id' => $current->id,
                    'product_material_id' => $current->product_material_id,
                    'quantity_grams' => $quantity,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'area_destination' => $input['area_destination'] ?? null,
                    'notes' => $input['notes'] ?? null,
                    'prepared_by' => $actor->id,
                    'prepared_at' => now(),
                ]);
                $movementNote = 'Preparación de pintura #'.$preparation->id.' · Fórmula v'.$current->version;
                foreach ($preview['components'] as $component) {
                    $volumeBased = in_array($component['unit'], ['ml', 'L'], true);
                    $densityNote = $component['density_g_per_ml'] === null ? '' : ' · Densidad '.$component['density_g_per_ml'].' g/ml';
                    $movement = $this->inventory->confirm($actor, [
                        'material_id' => $component['material_id'],
                        'movement_type' => 'exit',
                        'quantity_original' => $volumeBased ? $component['required_quantity'] : $component['grams'],
                        'unit_original' => $volumeBased ? $component['unit'] : 'gr',
                        'area_destination' => $input['area_destination'] ?? null,
                        'notes' => $movementNote.' · '.$component['grams'].' g'.$densityNote,
                        'idempotency_key' => (string) Str::uuid(),
                    ], null, 'paint_preparation')['movement'];
                    $preparation->components()->create([
                        'material_id' => $component['material_id'],
                        'inventory_movement_id' => $movement->id,
                        'grams' => $component['grams'],
                        'density_g_per_ml_snapshot' => $component['density_g_per_ml'],
                    ]);
                }
                $finished = $this->inventory->confirm($actor, [
                    'material_id' => $current->product_material_id,
                    'movement_type' => 'entry',
                    'quantity_original' => $quantity,
                    'unit_original' => 'gr',
                    'area_destination' => $input['area_destination'] ?? null,
                    'notes' => $movementNote,
                    'idempotency_key' => (string) Str::uuid(),
                ], null, 'paint_preparation')['movement'];
                $preparation->output_movement_id = $finished->id;
                $preparation->save();

                return ['preparation' => $preparation, 'replayed' => false];
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            if (($existing = $replay()) !== null) {
                return $existing;
            }
            throw $error;
        }
    }
}
