<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->decimal('unit_cost_mxn_snapshot', 18, 6)->nullable();
            $table->decimal('total_cost_mxn', 30, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost_mxn_snapshot', 'total_cost_mxn']);
        });
    }
};
