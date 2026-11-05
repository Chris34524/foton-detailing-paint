<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->decimal('minimum_stock', 18, 6)->nullable();
            $table->decimal('maximum_stock', 18, 6)->nullable();
            $table->string('location', 160)->nullable()->index();
            $table->string('supplier', 160)->nullable()->index();
            $table->text('notes')->nullable();
        });
        foreach (['kg' => 'Kg', 'l' => 'L', 'ml' => 'ml', 'g' => 'gr', 'gr' => 'gr', 'm' => 'mts', 'mts' => 'mts', 'cm' => 'cm', 'pza' => 'Pza', 'hoja' => 'hojas', 'hojas' => 'hojas'] as $alias => $unit) {
            DB::table('materials')->whereRaw('LOWER(TRIM(unit)) = ?', [$alias])->update(['unit' => $unit]);
        }
        Schema::create('inventory_sequences', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_value');
        });
        DB::table('inventory_sequences')->insert(['name' => 'movement_folio', 'next_value' => 1]);
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained()->restrictOnDelete();
            foreach (['quantity', 'opening_quantity', 'total_entries', 'total_exits', 'total_adjustments_in', 'total_adjustments_out'] as $column) {
                $table->decimal($column, 18, 6)->default(0);
            }
            $table->timestamps();
        });
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('movement_type')->index();
            $table->decimal('quantity_original', 18, 6);
            $table->string('unit_original', 24);
            $table->decimal('quantity_equivalent', 18, 6);
            $table->decimal('signed_quantity', 18, 6);
            $table->decimal('stock_before', 18, 6);
            $table->decimal('stock_after', 18, 6);
            $table->string('material_code_snapshot', 80);
            $table->string('material_name_snapshot', 160);
            $table->string('unit_snapshot', 24);
            $table->string('area_destination', 80)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('source_kind', 24);
            $table->unsignedBigInteger('opening_material_id')->nullable()->storedAs("CASE WHEN movement_type = 'opening_balance' THEN material_id ELSE NULL END")->unique('inventory_opening_once');
            $table->timestamps();
        });
        Schema::table('material_consumptions', function (Blueprint $table): void {
            $table->decimal('quantity', 18, 6)->change();
            $table->foreignId('inventory_movement_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_consumptions', function (Blueprint $table): void {
            $table->dropForeign(['inventory_movement_id']);
            $table->dropUnique(['inventory_movement_id']);
            $table->dropColumn('inventory_movement_id');
            $table->decimal('quantity', 12, 3)->change();
        });
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_sequences');
        Schema::table('materials', function (Blueprint $table): void {
            $table->dropIndex(['location']);
            $table->dropIndex(['supplier']);
            $table->dropColumn(['minimum_stock', 'maximum_stock', 'location', 'supplier', 'notes']);
        });
    }
};
