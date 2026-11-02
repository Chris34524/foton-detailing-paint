<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagnosis_id')->constrained()->restrictOnDelete();
            $table->foreignId('defect_type_id')->constrained()->restrictOnDelete();
            $table->string('location', 160);
            $table->text('description')->nullable();
            $table->string('priority')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
