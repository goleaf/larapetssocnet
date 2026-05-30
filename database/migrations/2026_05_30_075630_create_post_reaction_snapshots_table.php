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
        Schema::create('post_reaction_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedInteger('reactions_count')->default(0);
            $table->timestamps();

            $table->unique(['post_id', 'captured_at']);
            $table->index(['post_id', 'captured_at', 'reactions_count'], 'post_reaction_snapshots_post_time_count_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_reaction_snapshots');
    }
};
