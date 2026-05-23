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
        Schema::create('profile_portfolio_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('display_order');
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->unique(['user_id', 'display_order']);
            $table->index(['user_id', 'display_order', 'post_id'], 'portfolio_user_order_post_index');
            $table->index(['post_id', 'user_id'], 'portfolio_post_user_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_portfolio_posts');
    }
};
