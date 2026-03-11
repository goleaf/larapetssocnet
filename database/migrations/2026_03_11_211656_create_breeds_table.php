<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('species_slug')->nullable();
            $table->timestamps();

            $table->unique('slug');
            $table->index('name');
            $table->index(['species_slug', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeds');
    }
};
