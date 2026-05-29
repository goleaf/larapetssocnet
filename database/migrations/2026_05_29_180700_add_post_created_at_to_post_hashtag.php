<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('post_hashtag')) {
            return;
        }

        Schema::table('post_hashtag', function (Blueprint $table): void {
            if (! Schema::hasColumn('post_hashtag', 'post_created_at')) {
                $table->timestamp('post_created_at')->nullable()->after('hashtag_id');
            }
        });

        DB::statement('UPDATE post_hashtag SET post_created_at = (SELECT posts.created_at FROM posts WHERE posts.id = post_hashtag.post_id) WHERE post_created_at IS NULL');

        if (! $this->hasIndexByName('post_hashtag', 'post_hashtag_tag_created_post_index')) {
            Schema::table('post_hashtag', function (Blueprint $table): void {
                $table->index(['hashtag_id', 'post_created_at', 'post_id'], 'post_hashtag_tag_created_post_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('post_hashtag')) {
            return;
        }

        if ($this->hasIndexByName('post_hashtag', 'post_hashtag_tag_created_post_index')) {
            Schema::table('post_hashtag', function (Blueprint $table): void {
                $table->dropIndex('post_hashtag_tag_created_post_index');
            });
        }

        Schema::table('post_hashtag', function (Blueprint $table): void {
            if (Schema::hasColumn('post_hashtag', 'post_created_at')) {
                $table->dropColumn('post_created_at');
            }
        });
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
