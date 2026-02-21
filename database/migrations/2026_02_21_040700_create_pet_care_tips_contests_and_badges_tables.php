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
        Schema::create('pet_care_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('species')->nullable();
            $table->string('category')->nullable();
            $table->text('content');
            $table->boolean('is_approved')->default(false);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_approved', 'created_at']);
            $table->index(['species', 'created_at']);
        });

        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('prize')->nullable();
            $table->string('species')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('max_entries')->nullable();
            $table->unsignedInteger('entries_count')->default(0);
            $table->unsignedBigInteger('winner_entry_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
            $table->index(['organizer_user_id', 'created_at']);
        });

        Schema::create('contest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->text('caption')->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->unique(['contest_id', 'user_id']);
            $table->index(['contest_id', 'votes_count']);
        });

        Schema::create('contest_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('contest_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['entry_id', 'user_id']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('condition_type')->default('manual');
            $table->unsignedInteger('condition_value')->nullable();
            $table->timestamps();
        });

        Schema::create('badge_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_user');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('contest_votes');
        Schema::dropIfExists('contest_entries');
        Schema::dropIfExists('contests');
        Schema::dropIfExists('pet_care_tips');
    }
};
