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
        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('visibility')->default('public');
            $table->string('type')->default('text');
            $table->string('status')->default('published');
            $table->string('location')->nullable();
            $table->text('tagged_pets')->nullable();
            $table->text('metadata')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['pet_id', 'created_at']);
            $table->index(['visibility', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('post_hashtag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'hashtag_id']);
            $table->index('hashtag_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['parent_id', 'created_at']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reactable_type');
            $table->unsignedBigInteger('reactable_id');
            $table->string('type')->default('like');
            $table->timestamps();

            $table->unique(['user_id', 'reactable_type', 'reactable_id']);
            $table->index(['reactable_type', 'reactable_id']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->index(['post_id', 'type']);
        });

        Schema::create('saved_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('post_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->text('details')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_posts');
        Schema::dropIfExists('post_reports');
        Schema::dropIfExists('post_reactions');
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_hashtag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('hashtags');
    }
};
