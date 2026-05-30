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
        Schema::table('comments', function (Blueprint $table) {
            $table->string('gif_url')->nullable()->after('body_html');
            $table->string('gif_preview_url')->nullable()->after('gif_url');
            $table->string('gif_title')->nullable()->after('gif_preview_url');
            $table->string('gif_provider', 32)->nullable()->after('gif_title');
            $table->string('language_code', 12)->nullable()->after('gif_provider');
            $table->unsignedSmallInteger('quality_score')->default(0)->after('language_code');

            $table->index(['post_id', 'quality_score', 'created_at'], 'comments_post_quality_created_index');
            $table->index(['post_id', 'language_code'], 'comments_post_language_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_post_quality_created_index');
            $table->dropIndex('comments_post_language_index');
            $table->dropColumn([
                'gif_url',
                'gif_preview_url',
                'gif_title',
                'gif_provider',
                'language_code',
                'quality_score',
            ]);
        });
    }
};
