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
        if (! Schema::hasColumn('comments', 'depth')) {
            DB::statement('ALTER TABLE comments ADD COLUMN depth INTEGER NOT NULL DEFAULT 0 CHECK (depth >= 0 AND depth <= 2)');
        }

        if (! Schema::hasColumn('comments', 'is_pinned')) {
            DB::statement('ALTER TABLE comments ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0');
        }

        if (! Schema::hasColumn('comments', 'edit_count')) {
            DB::statement('ALTER TABLE comments ADD COLUMN edit_count INTEGER NOT NULL DEFAULT 0');
        }

        $this->backfillDepth();
        $this->backfillPinnedComments();

        DB::statement('CREATE INDEX IF NOT EXISTS comments_post_parent_index ON comments (post_id, parent_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS comments_post_author_index ON comments (post_id, user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS comments_depth_index ON comments (depth)');
        DB::statement('CREATE INDEX IF NOT EXISTS comments_post_pinned_index ON comments (post_id, is_pinned)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS comments_single_pinned_per_post_index ON comments (post_id) WHERE is_pinned = 1 AND deleted_at IS NULL');

        if (! Schema::hasTable('comment_mentions')) {
            Schema::create('comment_mentions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
                $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['comment_id', 'mentioned_user_id'], 'comment_mentions_comment_user_unique');
                $table->index(['mentioned_user_id', 'created_at'], 'comment_mentions_user_created_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS comments_single_pinned_per_post_index');
        DB::statement('DROP INDEX IF EXISTS comments_post_pinned_index');
        DB::statement('DROP INDEX IF EXISTS comments_depth_index');
        DB::statement('DROP INDEX IF EXISTS comments_post_author_index');
        DB::statement('DROP INDEX IF EXISTS comments_post_parent_index');

        Schema::dropIfExists('comment_mentions');

        Schema::table('comments', function ($table): void {
            foreach (['depth', 'is_pinned', 'edit_count'] as $column) {
                if (Schema::hasColumn('comments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillDepth(): void
    {
        $comments = DB::table('comments')
            ->select(['id', 'parent_id'])
            ->get()
            ->mapWithKeys(fn (object $comment): array => [
                (int) $comment->id => (int) ($comment->parent_id ?? 0),
            ]);

        $depthFor = function (int $commentId) use (&$depthFor, $comments): int {
            $parentId = (int) ($comments[$commentId] ?? 0);

            if ($parentId < 1) {
                return 0;
            }

            return min(2, $depthFor($parentId) + 1);
        };

        foreach ($comments->keys() as $commentId) {
            DB::table('comments')
                ->where('id', $commentId)
                ->update(['depth' => $depthFor((int) $commentId)]);
        }
    }

    private function backfillPinnedComments(): void
    {
        DB::table('posts')
            ->whereNotNull('metadata')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunk(100, function ($posts): void {
                foreach ($posts as $post) {
                    $metadata = json_decode((string) $post->metadata, true);
                    $pinnedCommentId = is_array($metadata) ? (int) ($metadata['pinned_comment_id'] ?? 0) : 0;

                    if ($pinnedCommentId < 1) {
                        continue;
                    }

                    DB::table('comments')
                        ->where('post_id', $post->id)
                        ->where('id', $pinnedCommentId)
                        ->update(['is_pinned' => 1]);
                }
            });
    }
};
