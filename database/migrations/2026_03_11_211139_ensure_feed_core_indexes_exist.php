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
                $table->index(['user_id', 'created_at'], 'feed_posts_user_id_created_at_lookup_index');
            });
        }

        if (Schema::hasTable('follows') && ! $this->hasIndex('follows', ['follower_id', 'following_id'])) {
            Schema::table('follows', function (Blueprint $table): void {
                $table->index(['follower_id', 'following_id'], 'feed_follows_follower_following_lookup_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('posts') && $this->hasIndexByName('posts', 'feed_posts_user_id_created_at_lookup_index')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropIndex('feed_posts_user_id_created_at_lookup_index');
            });
        }

        if (Schema::hasTable('follows') && $this->hasIndexByName('follows', 'feed_follows_follower_following_lookup_index')) {
            Schema::table('follows', function (Blueprint $table): void {
                $table->dropIndex('feed_follows_follower_following_lookup_index');
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasIndex(string $table, array $columns, ?bool $unique = null): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(function (array $index) use ($columns, $unique): bool {
                $matchesColumns = ($index['columns'] ?? []) === $columns;

                if (! $matchesColumns) {
                    return false;
                }

                if ($unique === null) {
                    return true;
                }

                return (bool) ($index['is_unique'] ?? false) === $unique;
            });
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
