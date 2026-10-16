<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name', 120); $table->string('email', 254)->unique(); $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); $table->string('role', 40)->default('user'); $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable(); $table->rememberToken(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};
