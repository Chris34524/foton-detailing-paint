<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->string('kind', 24)->default('raw_material')->index();
        });

        Schema::create('paint_formulas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_material_id')->constrained('materials')->restrictOnDelete();
            $table->string('vehicle_model', 100);
            $table->string('color_name', 160);
            $table->string('color_code', 60);
            $table->unsignedInteger('version');
            $table->decimal('total_grams', 18, 2);
            $table->boolean('is_current')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vehicle_model', 'color_code', 'version'], 'paint_formula_model_color_version_uq');
            $table->unique(['product_material_id', 'version'], 'paint_formula_product_version_uq');
        });

        Schema::create('paint_formula_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paint_formula_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->string('material_code_snapshot', 80);
            $table->string('material_name_snapshot', 160);
            $table->decimal('grams', 18, 2);
            $table->timestamps();
            $table->unique(['paint_formula_id', 'material_id'], 'paint_formula_component_uq');
        });

        Schema::create('paint_preparations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paint_formula_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('quantity_grams', 18, 2);
            $table->foreignId('output_movement_id')->nullable()->unique()->constrained('inventory_movements')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('area_destination', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at');
            $table->timestamps();
        });

        Schema::create('paint_preparation_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paint_preparation_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->unique()->constrained('inventory_movements')->restrictOnDelete();
            $table->decimal('grams', 18, 2);
            $table->timestamps();
            $table->unique(['paint_preparation_id', 'material_id'], 'paint_preparation_component_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paint_preparation_components');
        Schema::dropIfExists('paint_preparations');
        Schema::dropIfExists('paint_formula_components');
        Schema::dropIfExists('paint_formulas');
        Schema::table('materials', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
