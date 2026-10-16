<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 180)->unique();
            $table->string('page_type', 40)->default('standard')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('show_in_menu')->default(false)->index();
            $table->json('sections')->nullable();
            $table->json('published_sections')->nullable();
            $table->string('seo_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->boolean('noindex')->default(true);
            $table->string('published_seo_title', 180)->nullable();
            $table->string('published_meta_description', 320)->nullable();
            $table->string('published_canonical_url', 500)->nullable();
            $table->boolean('published_noindex')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_page_id')->constrained('site_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('event', 30)->default('saved');
            $table->string('change_summary', 500)->nullable();
            $table->json('snapshot');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['site_page_id', 'version']);
        });

        Schema::create('site_navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 20)->default('header')->index();
            $table->string('label', 100);
            $table->foreignId('site_page_id')->nullable()->constrained('site_pages')->nullOnDelete();
            $table->string('external_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->json('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('site_page_id')->nullable()->after('experience_id')->constrained('site_pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_page_id');
        });
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('site_navigation_items');
        Schema::dropIfExists('site_page_revisions');
        Schema::dropIfExists('site_pages');
    }
};
