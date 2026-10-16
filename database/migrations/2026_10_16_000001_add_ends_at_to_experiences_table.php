<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table): void {
            $table->timestamp('ends_at')->nullable()->index()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table): void {
            $table->dropIndex(['ends_at']);
            $table->dropColumn('ends_at');
        });
    }
};
