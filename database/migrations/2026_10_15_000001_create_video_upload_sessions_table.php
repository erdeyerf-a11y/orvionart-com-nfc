<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_upload_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('experience_id')->nullable()->constrained('experiences')->nullOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title', 160)->nullable();
            $table->string('alt_text', 250)->nullable();
            $table->string('original_name', 255);
            $table->string('declared_mime', 120);
            $table->unsignedBigInteger('total_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->json('received_chunks');
            $table->json('chunk_hashes');
            $table->string('status', 20)->default('uploading')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['uploaded_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_upload_sessions');
    }
};
