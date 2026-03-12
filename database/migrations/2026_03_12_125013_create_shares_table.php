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
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shareable_type');
            $table->unsignedBigInteger('shareable_id');
            $table->string('method')->default('copy_link');
            $table->timestamps();

            $table->unique(['user_id', 'shareable_type', 'shareable_id']);
            $table->index(['shareable_type', 'shareable_id']);
            $table->index(['method', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
