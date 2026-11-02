<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('defect_id')->constrained()->restrictOnDelete();
            $table->string('disk', 100);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_evidences');
    }
};
