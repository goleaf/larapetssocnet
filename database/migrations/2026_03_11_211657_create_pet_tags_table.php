<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['pet_id', 'slug']);
            $table->index(['slug', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_tags');
    }
};
