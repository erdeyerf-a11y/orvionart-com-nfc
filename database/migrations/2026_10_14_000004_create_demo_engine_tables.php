<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('demo_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 40)->default('default')->unique();
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedSmallInteger('journey_days')->default(7);
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->string('title', 180)->default('ORVIONART Demo');
            $table->string('paused_message', 500)->default('Demo şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.');
            $table->timestamps();
        });

        Schema::create('demo_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('summary', 500)->nullable();
            $table->longText('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('media_disk', 40)->nullable();
            $table->string('media_path', 500)->nullable();
            $table->string('media_mime_type', 120)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('device_token_hash', 64)->unique();
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_daily_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demo_session_id')->constrained('demo_sessions')->cascadeOnDelete();
            $table->foreignId('demo_content_id')->constrained('demo_contents')->restrictOnDelete();
            $table->date('assigned_for');
            $table->timestamps();
            $table->unique(['demo_session_id', 'assigned_for'], 'demo_session_day_unique');
            $table->index(['assigned_for', 'demo_content_id']);
        });

        Schema::create('demo_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demo_session_id')->nullable()->constrained('demo_sessions')->nullOnDelete();
            $table->string('event_type', 60)->index();
            $table->foreignId('demo_content_id')->nullable()->constrained('demo_contents')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_events');
        Schema::dropIfExists('demo_daily_assignments');
        Schema::dropIfExists('demo_sessions');
        Schema::dropIfExists('demo_contents');
        Schema::dropIfExists('demo_settings');
    }
};
