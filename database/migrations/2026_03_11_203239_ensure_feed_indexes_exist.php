<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts') && ! $this->hasIndex('posts', ['user_id', 'created_at'])) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->index(['user_id', 'created_at'], 'feed_posts_user_id_created_at_index');
            });
        }

        if (Schema::hasTable('follows') && ! $this->hasIndex('follows', ['follower_id', 'following_id'], true)) {
            Schema::table('follows', function (Blueprint $table): void {
                $table->unique(['follower_id', 'following_id'], 'feed_follows_follower_id_following_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('posts') && $this->hasIndexByName('posts', 'feed_posts_user_id_created_at_index')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropIndex('feed_posts_user_id_created_at_index');
            });
        }

        if (Schema::hasTable('follows') && $this->hasIndexByName('follows', 'feed_follows_follower_id_following_id_unique')) {
            Schema::table('follows', function (Blueprint $table): void {
                $table->dropUnique('feed_follows_follower_id_following_id_unique');
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['columns'] ?? []) === $columns
                && (bool) ($index['is_unique'] ?? false) === $unique);
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
