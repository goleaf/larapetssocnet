<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }
        });

        DB::table('posts')
            ->whereNull('uuid')
            ->orderBy('id')
            ->select(['id'])
            ->lazyById()
            ->each(function (object $post): void {
                DB::table('posts')
                    ->where('id', $post->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

        DB::table('posts')
            ->where('author_type', 'App\\Models\\User')
            ->update(['author_type' => 'App\\Models\\Identity\\User']);

        $this->ensureIndex('posts', ['uuid'], 'posts_uuid_unique', unique: true);
        $this->ensureIndex('posts', ['author_type', 'author_id'], 'posts_author_identity_index');
        $this->ensureIndex('posts', ['author_type', 'author_id', 'created_at'], 'posts_author_lookup_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'posts_author_identity_index',
            'posts_uuid_unique',
        ] as $indexName) {
            if ($this->hasIndexByName('posts', $indexName)) {
                Schema::table('posts', function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }

        Schema::table('posts', function (Blueprint $table): void {
            if (Schema::hasColumn('posts', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $table, array $columns, string $name, bool $unique = false): void
    {
        if ($this->hasIndexByName($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name, $unique): void {
            if ($unique) {
                $table->unique($columns, $name);

                return;
            }

            $table->index($columns, $name);
        });
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
