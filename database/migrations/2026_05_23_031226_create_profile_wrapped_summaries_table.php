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
        Schema::create('profile_wrapped_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('total_posts_published')->default(0);
            $table->unsignedInteger('total_reactions_received')->default(0);
            $table->string('top_reaction_type')->nullable();
            $table->unsignedInteger('top_reaction_count')->default(0);
            $table->unsignedTinyInteger('most_active_month')->nullable();
            $table->unsignedInteger('most_active_month_posts')->default(0);
            $table->unsignedInteger('new_followers_count')->default(0);
            $table->unsignedInteger('pets_added_count')->default(0);
            $table->foreignId('most_engaged_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->unsignedInteger('most_engaged_post_score')->default(0);
            $table->string('share_image_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('share_image_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year']);
            $table->index(['year', 'generated_at']);
            $table->index(['user_id', 'year', 'share_image_generated_at'], 'wrapped_user_year_image_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_wrapped_summaries');
    }
};
