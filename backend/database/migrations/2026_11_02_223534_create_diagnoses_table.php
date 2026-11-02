<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('diagnosed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
