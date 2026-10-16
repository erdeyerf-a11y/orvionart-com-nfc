<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_rights', function (Blueprint $table): void {
            $table->id();
            $table->string('right_type', 50)->index();
            $table->string('label', 180);
            // Deliberately generic: the business decision about which entity owns a right is unresolved.
            $table->string('subject_type', 100)->default('unresolved')->index();
            $table->string('subject_reference', 190)->nullable()->index();
            $table->string('grant_source', 60)->default('manual_admin')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_rights');
    }
};
