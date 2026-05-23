<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_security_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_type')->default('password_reset_emergency_lock');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action_type', 'created_at']);
            $table->index(['used_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_security_actions');
    }
};
