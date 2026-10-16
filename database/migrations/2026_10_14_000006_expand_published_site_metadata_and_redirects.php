<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table): void {
            $table->string('published_title', 180)->nullable()->after('published_sections');
            $table->string('published_slug', 180)->nullable()->unique()->after('published_title');
            $table->string('published_page_type', 40)->nullable()->after('published_slug');
            $table->boolean('published_show_in_menu')->nullable()->after('published_page_type');
            $table->unsignedBigInteger('social_image_asset_id')->nullable()->after('canonical_url');
            $table->unsignedBigInteger('published_social_image_asset_id')->nullable()->after('published_canonical_url');
        });

        // Safe migration for a database that already contains published pages.
        DB::table('site_pages')->where('status', 'published')->whereNotNull('published_sections')->update([
            'published_title' => DB::raw('title'),
            'published_slug' => DB::raw('slug'),
            'published_page_type' => DB::raw('page_type'),
            'published_show_in_menu' => DB::raw('show_in_menu'),
        ]);

        Schema::create('site_page_redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_page_id')->constrained('site_pages')->cascadeOnDelete();
            $table->string('old_slug', 180)->unique();
            $table->string('new_slug', 180)->index();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_redirects');
        Schema::table('site_pages', function (Blueprint $table): void {
            $table->dropUnique(['published_slug']);
            $table->dropColumn([
                'published_title', 'published_slug', 'published_page_type', 'published_show_in_menu',
                'social_image_asset_id', 'published_social_image_asset_id',
            ]);
        });
    }
};
