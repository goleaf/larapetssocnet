<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('username_redirects')) {
            return;
        }

        Schema::create('username_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('old_username', 30)->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('redirects_until');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('username_redirects');
    }
};
