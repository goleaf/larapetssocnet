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
        Schema::create('pet_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('milestone_type')->default('life_event');
            $table->string('title');
            $table->text('body')->nullable();
            $table->text('body_html')->nullable();
            $table->date('occurred_on');
            $table->boolean('share_as_post')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pet_id', 'occurred_on']);
            $table->index(['pet_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_milestones');
    }
};
