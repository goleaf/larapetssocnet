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
        if (! Schema::hasTable('post_media') || $this->hasIndexByName('post_media', 'post_media_post_order_index')) {
            return;
        }

        Schema::table('post_media', function (Blueprint $table): void {
            $table->index(['post_id', 'order'], 'post_media_post_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('post_media') || ! $this->hasIndexByName('post_media', 'post_media_post_order_index')) {
            return;
        }

        Schema::table('post_media', function (Blueprint $table): void {
            $table->dropIndex('post_media_post_order_index');
        });
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
