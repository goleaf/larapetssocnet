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
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('species');
            $table->string('breed')->nullable();
            $table->string('sex')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['species', 'created_at']);
            $table->index(['is_public', 'created_at']);
        });

        Schema::create('user_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
            $table->index(['following_id', 'created_at']);
            $table->index(['follower_id', 'created_at']);
        });

        Schema::create('pet_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'pet_id']);
            $table->index(['pet_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('pet_followers');
        Schema::dropIfExists('user_follows');
        Schema::dropIfExists('pets');
    }
};
