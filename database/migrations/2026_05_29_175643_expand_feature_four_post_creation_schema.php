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
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('posts', 'author_type')) {
                    $table->string('author_type')->nullable()->after('user_id');
                }

                if (! Schema::hasColumn('posts', 'author_id')) {
                    $table->unsignedBigInteger('author_id')->nullable()->after('author_type');
                }

                if (! Schema::hasColumn('posts', 'mood')) {
                    $table->enum('mood', ['happy', 'excited', 'proud', 'worried', 'sad', 'grateful', 'playful'])->nullable()->after('visibility');
                }

                if (! Schema::hasColumn('posts', 'location_display_text')) {
                    $table->string('location_display_text', 120)->nullable()->after('location');
                }

                if (! Schema::hasColumn('posts', 'link_preview')) {
                    $table->json('link_preview')->nullable()->after('metadata');
                }

                if (! Schema::hasColumn('posts', 'scheduled_publish_at')) {
                    $table->timestamp('scheduled_publish_at')->nullable()->after('published_at');
                }

                if (! Schema::hasColumn('posts', 'edit_count')) {
                    $table->unsignedInteger('edit_count')->default(0)->after('edited_at');
                }

                if (! Schema::hasColumn('posts', 'view_count')) {
                    $table->unsignedInteger('view_count')->default(0)->after('shares_count');
                }

                foreach (['love', 'cute', 'funny', 'wow', 'sad', 'support'] as $reactionType) {
                    $column = "{$reactionType}_count";

                    if (! Schema::hasColumn('posts', $column)) {
                        $table->unsignedInteger($column)->default(0)->after('reactions_count');
                    }
                }

                if (! Schema::hasColumn('posts', 'original_post_id')) {
                    $table->unsignedBigInteger('original_post_id')->nullable()->after('system_source');
                }

                if (! Schema::hasColumn('posts', 'quote_post_id')) {
                    $table->unsignedBigInteger('quote_post_id')->nullable()->after('original_post_id');
                }
            });

            DB::table('posts')
                ->whereNull('author_type')
                ->update(['author_type' => 'App\\Models\\Identity\\User']);

            DB::statement('UPDATE posts SET author_id = user_id WHERE author_id IS NULL');
            DB::statement("UPDATE posts SET scheduled_publish_at = published_at WHERE scheduled_publish_at IS NULL AND status = 'scheduled'");
            DB::statement('UPDATE posts SET location_display_text = location WHERE location_display_text IS NULL AND location IS NOT NULL');

            $this->ensureIndex('posts', ['author_type', 'author_id', 'created_at'], 'posts_author_lookup_index');
            $this->ensureIndex('posts', ['status', 'scheduled_publish_at'], 'posts_status_scheduled_publish_at_index');
            $this->ensureIndex('posts', ['original_post_id'], 'posts_original_post_id_index');
            $this->ensureIndex('posts', ['quote_post_id'], 'posts_quote_post_id_index');
        }

        if (Schema::hasTable('post_hashtag')) {
            $this->ensureIndex('post_hashtag', ['hashtag_id', 'post_id'], 'post_hashtag_hashtag_post_index');
        }

        if (! Schema::hasTable('post_mentions')) {
            Schema::create('post_mentions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('post_id')->constrained()->cascadeOnDelete();
                $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('mentioned_username', 30);
                $table->timestamps();

                $table->unique(['post_id', 'mentioned_user_id']);
                $table->index(['mentioned_user_id', 'created_at'], 'post_mentions_user_created_index');
            });
        }

        if (! Schema::hasTable('post_drafts')) {
            Schema::create('post_drafts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('context_type')->default('default');
                $table->unsignedBigInteger('context_id')->default(0);
                $table->text('body')->nullable();
                $table->string('visibility')->default('public');
                $table->string('mood', 50)->nullable();
                $table->string('location', 120)->nullable();
                $table->decimal('location_lat', 10, 7)->nullable();
                $table->decimal('location_lng', 10, 7)->nullable();
                $table->text('tagged_pets')->nullable();
                $table->text('media_payload')->nullable();
                $table->text('link_preview')->nullable();
                $table->timestamp('scheduled_publish_at')->nullable();
                $table->timestamp('last_autosaved_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'context_type', 'context_id'], 'post_drafts_user_context_unique');
                $table->index(['user_id', 'updated_at'], 'post_drafts_user_updated_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_drafts');
        Schema::dropIfExists('post_mentions');

        if (Schema::hasTable('post_hashtag') && $this->hasIndexByName('post_hashtag', 'post_hashtag_hashtag_post_index')) {
            Schema::table('post_hashtag', function (Blueprint $table): void {
                $table->dropIndex('post_hashtag_hashtag_post_index');
            });
        }

        if (! Schema::hasTable('posts')) {
            return;
        }

        foreach ([
            'posts_author_lookup_index',
            'posts_status_scheduled_publish_at_index',
            'posts_original_post_id_index',
            'posts_quote_post_id_index',
        ] as $indexName) {
            if ($this->hasIndexByName('posts', $indexName)) {
                Schema::table('posts', function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }

        Schema::table('posts', function (Blueprint $table): void {
            foreach ([
                'author_type',
                'author_id',
                'mood',
                'location_display_text',
                'link_preview',
                'scheduled_publish_at',
                'edit_count',
                'view_count',
                'love_count',
                'cute_count',
                'funny_count',
                'wow_count',
                'sad_count',
                'support_count',
                'original_post_id',
                'quote_post_id',
            ] as $column) {
                if (Schema::hasColumn('posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if ($this->hasIndexByName($table, $name) || $this->hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasIndex(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['columns'] ?? []) === $columns);
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
