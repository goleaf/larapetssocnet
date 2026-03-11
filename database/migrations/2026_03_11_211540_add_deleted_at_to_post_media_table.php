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
        if (! Schema::hasTable('post_media') || Schema::hasColumn('post_media', 'deleted_at')) {
            return;
        }

        Schema::table('post_media', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('post_media') || ! Schema::hasColumn('post_media', 'deleted_at')) {
            return;
        }

        Schema::table('post_media', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
