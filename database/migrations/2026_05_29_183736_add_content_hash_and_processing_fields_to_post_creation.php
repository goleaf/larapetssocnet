<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'content_hash')) {
                $table->char('content_hash', 64)->nullable()->after('body');
            }
        });

        DB::table('posts')
            ->select(['id', 'body'])
            ->whereNull('content_hash')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $post): void {
                $normalized = $this->normalizeContentHashInput($post->body);

                if ($normalized === '') {
                    return;
                }

                DB::table('posts')
                    ->where('id', $post->id)
                    ->update(['content_hash' => hash('sha256', $normalized)]);
            });

        $this->ensureIndex('posts', ['author_type', 'author_id', 'content_hash', 'created_at'], 'posts_author_content_hash_created_index');

        if (Schema::hasTable('post_media')) {
            Schema::table('post_media', function (Blueprint $table): void {
                if (! Schema::hasColumn('post_media', 'alt_text')) {
                    $table->string('alt_text', 160)->nullable()->after('media_type');
                }

                if (! Schema::hasColumn('post_media', 'processing_status')) {
                    $table->string('processing_status')->default('processed')->after('alt_text');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('post_media')) {
            Schema::table('post_media', function (Blueprint $table): void {
                if (Schema::hasColumn('post_media', 'processing_status')) {
                    $table->dropColumn('processing_status');
                }

                if (Schema::hasColumn('post_media', 'alt_text')) {
                    $table->dropColumn('alt_text');
                }
            });
        }

        Schema::table('posts', function (Blueprint $table): void {
            if ($this->hasIndexByName('posts', 'posts_author_content_hash_created_index')) {
                $table->dropIndex('posts_author_content_hash_created_index');
            }

            if (Schema::hasColumn('posts', 'content_hash')) {
                $table->dropColumn('content_hash');
            }
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if ($this->hasIndexByName($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    private function normalizeContentHashInput(mixed $body): string
    {
        $text = strip_tags((string) $body);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim(Str::lower($text));
    }
};
