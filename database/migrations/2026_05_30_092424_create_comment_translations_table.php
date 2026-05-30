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
        Schema::create('comment_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->string('source_language', 12)->nullable();
            $table->string('target_language', 12);
            $table->text('translated_body');
            $table->string('provider', 32)->nullable();
            $table->timestamp('cached_at')->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'target_language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_translations');
    }
};
