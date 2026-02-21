<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reserved_usernames')) {
            return;
        }

        Schema::create('reserved_usernames', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 30)->unique();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_usernames');
    }
};

