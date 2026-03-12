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

        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            }

            if (! Schema::hasColumn('posts', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('body_html');
            }

            if (! Schema::hasColumn('posts', 'save_count')) {
                $table->unsignedInteger('save_count')->default(0)->after('shares_count');
            }
        });

        if (! $this->hasIndex('posts', ['status', 'published_at'])) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->index(['status', 'published_at'], 'posts_status_published_at_index');
            });
        }

        if (! $this->hasIndex('posts', ['user_id', 'is_pinned', 'pinned_at'])) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->index(['user_id', 'is_pinned', 'pinned_at'], 'posts_user_id_pinned_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if ($this->hasIndexByName('posts', 'posts_status_published_at_index')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropIndex('posts_status_published_at_index');
            });
        }

        if ($this->hasIndexByName('posts', 'posts_user_id_pinned_at_index')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropIndex('posts_user_id_pinned_at_index');
            });
        }

        Schema::table('posts', function (Blueprint $table): void {
            if (Schema::hasColumn('posts', 'save_count')) {
                $table->dropColumn('save_count');
            }

            if (Schema::hasColumn('posts', 'edited_at')) {
                $table->dropColumn('edited_at');
            }

            if (Schema::hasColumn('posts', 'pinned_at')) {
                $table->dropColumn('pinned_at');
            }
        });
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
