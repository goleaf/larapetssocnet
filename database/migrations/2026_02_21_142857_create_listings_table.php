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
        Schema::create('listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('slug')->unique();
            $table->enum('type', ['sale', 'rehoming', 'wanted', 'service']);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->boolean('price_negotiable')->default(false);
            $table->string('location')->nullable();
            $table->enum('status', ['draft', 'active', 'sold', 'archived'])->default('draft');
            $table->unsignedInteger('views_count')->default(0);
            $table->string('pet_species')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
