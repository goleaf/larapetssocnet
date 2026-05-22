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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'post_reactions_received_count')) {
                $table->unsignedInteger('post_reactions_received_count')->default(0)->after('scheduled_posts_count');
            }

            if (! Schema::hasColumn('users', 'post_comments_received_count')) {
                $table->unsignedInteger('post_comments_received_count')->default(0)->after('post_reactions_received_count');
            }

            if (! Schema::hasColumn('users', 'last_post_created_at')) {
                $table->dateTime('last_post_created_at')->nullable()->after('post_comments_received_count');
            }
        });

        if (Schema::hasTable('posts')) {
            if (Schema::hasColumn('users', 'post_reactions_received_count')) {
                DB::statement(
                    'UPDATE users SET post_reactions_received_count = (
                        SELECT COALESCE(SUM(posts.reactions_count), 0)
                        FROM posts
                        WHERE posts.user_id = users.id
                            AND posts.deleted_at IS NULL
                    )'
                );
            }

            if (Schema::hasColumn('users', 'post_comments_received_count')) {
                DB::statement(
                    'UPDATE users SET post_comments_received_count = (
                        SELECT COALESCE(SUM(posts.comments_count), 0)
                        FROM posts
                        WHERE posts.user_id = users.id
                            AND posts.deleted_at IS NULL
                    )'
                );
            }

            if (Schema::hasColumn('users', 'last_post_created_at')) {
                DB::statement(
                    'UPDATE users SET last_post_created_at = (
                        SELECT MAX(posts.created_at)
                        FROM posts
                        WHERE posts.user_id = users.id
                            AND posts.deleted_at IS NULL
                    )'
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'last_post_created_at')) {
                $table->dropColumn('last_post_created_at');
            }

            if (Schema::hasColumn('users', 'post_comments_received_count')) {
                $table->dropColumn('post_comments_received_count');
            }

            if (Schema::hasColumn('users', 'post_reactions_received_count')) {
                $table->dropColumn('post_reactions_received_count');
            }
        });
    }
};
