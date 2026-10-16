<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('processing_status', 20)->default('ready')->index();
            $table->text('processing_error')->nullable();
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->decimal('duration_seconds', 10, 3)->nullable();
            $table->unsignedInteger('video_width')->nullable();
            $table->unsignedInteger('video_height')->nullable();
            $table->string('video_codec', 100)->nullable();
            $table->string('audio_codec', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['processing_status']);
            $table->dropColumn([
                'processing_status', 'processing_error', 'processing_attempts', 'processed_at',
                'duration_seconds', 'video_width', 'video_height', 'video_codec', 'audio_codec',
            ]);
        });
    }
};
