<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('experience_id')->nullable()->constrained('experiences')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160)->nullable();
            $table->string('alt_text', 250)->nullable();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('kind', 20)->index();
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
            $table->index(['experience_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
