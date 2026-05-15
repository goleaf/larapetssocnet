<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->unsignedBigInteger('awarded_by')->nullable();
            $table->string('note', 200)->nullable();
            $table->primary(['user_id', 'badge_id']);
            $table->index('awarded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
