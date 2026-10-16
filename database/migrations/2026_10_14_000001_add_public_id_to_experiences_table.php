<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table): void {
            $table->string('public_id', 48)->nullable()->unique();
        });

        // Backfill existing records without changing their numeric primary keys.
        DB::table('experiences')->whereNull('public_id')->orderBy('id')->select('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('experiences')->where('id', $row->id)->whereNull('public_id')->update([
                    'public_id' => Str::random(40),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
