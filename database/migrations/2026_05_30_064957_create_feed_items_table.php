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
        if (Schema::hasTable('feed_items')) {
            return;
        }

        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->timestamp('post_created_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'post_id', 'source_type', 'source_id'], 'feed_items_user_post_source_unique');
            $table->index(['user_id', 'post_created_at', 'post_id'], 'feed_items_user_created_post_index');
            $table->index(['user_id', 'source_type', 'source_id', 'post_created_at'], 'feed_items_user_source_created_index');
            $table->index(['user_id', 'source_type', 'post_created_at', 'post_id'], 'feed_items_user_source_created_post_index');
            $table->index('post_id', 'feed_items_post_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
