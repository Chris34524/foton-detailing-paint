<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Services\InventoryStockService;
use Illuminate\Console\Command;

class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile {--material= : Material ID} {--repair : Rebuild the balance cache from immutable movements}';

    protected $description = 'Compare inventory balance caches with the exact movement ledger';

    public function handle(InventoryStockService $stock): int
    {
        $query = Material::query()->orderBy('id');
        if ($this->option('material') !== null) {
            if (! ctype_digit((string) $this->option('material')) || (int) $this->option('material') < 1) {
                $this->error('Material ID must be a positive integer.');

                return self::INVALID;
            }
            $query->whereKey($this->option('material'));
            if (! $query->exists()) {
                $this->error('Material not found.');

                return self::INVALID;
            }
        }
        $mismatches = 0;
        foreach ($query->cursor() as $material) {
            $result = $stock->reconcile($material, (bool) $this->option('repair'));
            $this->line($material->code.': '.($result['repaired'] ? 'REPAIRED' : ($result['reconciled'] ? 'OK' : 'MISMATCH')));
            if (! $result['reconciled']) {
                $mismatches++;
            }
        }

        return $mismatches === 0 ? self::SUCCESS : self::FAILURE;
    }
}
