<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('actor_id')->nullable()->index(); $table->string('action', 120)->index();
            $table->string('subject_type', 120)->nullable(); $table->string('subject_id', 80)->nullable(); $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable(); $table->json('metadata')->nullable(); $table->timestamp('created_at')->useCurrent()->index();
        });
    }
    public function down(): void { Schema::dropIfExists('audit_logs'); }
};
