<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $highestProduct = DB::table('materials')->pluck('code')->reduce(function (int $max, string $code): int {
            return preg_match('/^PROD-(\\d+)$/', $code, $matches) ? max($max, (int) $matches[1]) : $max;
        }, 0);
        $highestMovement = DB::table('inventory_movements')->pluck('folio')->reduce(function (int $max, string $folio): int {
            return preg_match('/^MOV-(\\d+)$/', $folio, $matches) ? max($max, (int) $matches[1]) : $max;
        }, 0);

        foreach (['product_code' => $highestProduct, 'movement_folio_v2' => $highestMovement] as $name => $max) {
            DB::table('inventory_sequences')->insertOrIgnore(['name' => $name, 'next_value' => $max + 1]);
        }
    }

    public function down(): void
    {
        DB::table('inventory_sequences')->whereIn('name', ['product_code', 'movement_folio_v2'])->delete();
    }
};
