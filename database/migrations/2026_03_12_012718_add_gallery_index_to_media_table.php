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
        if (! Schema::hasTable('media')) {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->index(
                ['model_type', 'model_id', 'collection_name', 'order_column'],
                'media_model_collection_order_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_model_collection_order_index');
        });
    }
};
