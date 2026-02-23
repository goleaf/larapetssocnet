<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_galleries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->timestamps();

            $table->foreign('cover_media_id')
                ->references('id')
                ->on('media')
                ->nullOnDelete();
        });

        Schema::create('photo_gallery_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_id')
                ->constrained('photo_galleries')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('media_id');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('media_id')
                ->references('id')
                ->on('media')
                ->cascadeOnDelete();

            $table->unique(['gallery_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_gallery_media');
        Schema::dropIfExists('photo_galleries');
    }
};
