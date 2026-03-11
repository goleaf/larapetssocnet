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
        if (! Schema::hasTable('posts')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasColumn('posts', 'search_vector')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->tsvector('search_vector')->nullable();
            $table->index('search_vector', 'posts_search_vector_index', 'gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('posts', 'search_vector')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_search_vector_index');
            $table->dropColumn('search_vector');
        });
    }
};
