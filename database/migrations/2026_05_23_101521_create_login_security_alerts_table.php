<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('login_security_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('country_code', 8)->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->string('device_type', 16);
            $table->string('browser_name');
            $table->string('browser_version')->nullable();
            $table->string('os_name');
            $table->string('os_version')->nullable();
            $table->timestamp('login_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('secured_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'country_code', 'created_at']);
            $table->index(['dismissed_at', 'secured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_security_alerts');
    }
};
