<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->decimal('density_g_per_ml', 12, 6)->nullable();
        });

        Schema::table('paint_preparation_components', function (Blueprint $table): void {
            $table->decimal('density_g_per_ml_snapshot', 12, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('paint_preparation_components', function (Blueprint $table): void {
            $table->dropColumn('density_g_per_ml_snapshot');
        });

        Schema::table('materials', function (Blueprint $table): void {
            $table->dropColumn('density_g_per_ml');
        });
    }
};
