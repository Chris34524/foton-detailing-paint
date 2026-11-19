<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_mutexes', function (Blueprint $table): void {
            $table->string('name')->primary();
        });
        DB::table('operation_mutexes')->insert(['name' => 'process_sequence']);
        Schema::create('processes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('process_sequence_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('process_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->unique();
            $table->timestamps();
        });
        Schema::create('work_order_process_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('process_id')->constrained()->restrictOnDelete();
            $table->string('process_code_snapshot', 80);
            $table->string('process_name_snapshot', 160);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['work_order_id', 'position']);
            $table->unique(['work_order_id', 'process_id']);
        });
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('current_process_step_id')->nullable()->constrained('work_order_process_steps')->restrictOnDelete();
        });
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('unit', 24)->index();
            $table->string('warehouse_reference', 120)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('material_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_process_step_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('material_code_snapshot', 80);
            $table->string('material_name_snapshot', 160);
            $table->string('unit_snapshot', 24);
            $table->decimal('quantity', 12, 3);
            $table->text('notes')->nullable();
            $table->foreignId('consumed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consumed_at')->index();
            $table->string('external_reference', 120)->nullable()->index();
            $table->timestamps();
            $table->index('updated_at');
        });
        Schema::create('inspection_checklists', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('inspection_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_checklist_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('label', 300);
            $table->text('description')->nullable();
            $table->integer('sort_order');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['inspection_checklist_id', 'code']);
            $table->unique(['inspection_checklist_id', 'sort_order'], 'checklist_items_order_unique');
        });
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_checklist_id')->constrained()->restrictOnDelete();
            $table->string('checklist_name_snapshot', 160);
            $table->string('status')->default('draft')->index();
            $table->string('result')->nullable()->index();
            $table->unsignedBigInteger('draft_work_order_id')->nullable()->storedAs("CASE WHEN status = 'draft' THEN work_order_id ELSE NULL END")->unique();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('inspection_item_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->restrictOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained('inspection_checklist_items')->nullOnDelete();
            $table->string('item_code_snapshot', 80);
            $table->string('item_label_snapshot', 300);
            $table->text('item_description_snapshot')->nullable();
            $table->unsignedInteger('sort_order_snapshot');
            $table->boolean('is_required_snapshot');
            $table->string('result')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['inspection_id', 'checklist_item_id']);
        });
        Schema::create('reworks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->text('cause');
            $table->string('status')->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('unit_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['unit_releases', 'reworks', 'inspection_item_results', 'inspections', 'inspection_checklist_items', 'inspection_checklists', 'material_consumptions', 'materials'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_process_step_id');
        });
        foreach (['work_order_process_steps', 'process_sequence_steps', 'processes', 'operation_mutexes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
