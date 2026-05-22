<?php

use App\Enums\PostStatus;
use App\Models\Identity\User;
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
            if (! Schema::hasColumn('users', 'photos_count')) {
                $table->unsignedInteger('photos_count')->default(0)->after('posts_count');
            }

            if (! Schema::hasColumn('users', 'scheduled_posts_count')) {
                $table->unsignedInteger('scheduled_posts_count')->default(0)->after('photos_count');
            }
        });

        if (Schema::hasColumn('users', 'photos_count') && Schema::hasTable('media')) {
            $userMorphTypes = collect([
                User::class,
                (new User)->getMorphClass(),
                'App\Models\User',
            ])
                ->unique()
                ->map(fn (string $type): string => DB::getPdo()->quote($type))
                ->implode(', ');

            DB::statement(
                "UPDATE users SET photos_count = (
                    SELECT COUNT(*)
                    FROM media
                    WHERE media.model_id = users.id
                        AND media.model_type IN ({$userMorphTypes})
                        AND media.collection_name = 'photos'
                )"
            );
        }

        if (Schema::hasColumn('users', 'scheduled_posts_count') && Schema::hasTable('posts')) {
            $scheduledStatus = DB::getPdo()->quote(PostStatus::Scheduled->value);

            DB::statement(
                "UPDATE users SET scheduled_posts_count = (
                    SELECT COUNT(*)
                    FROM posts
                    WHERE posts.user_id = users.id
                        AND posts.status = {$scheduledStatus}
                        AND posts.deleted_at IS NULL
                )"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'scheduled_posts_count')) {
                $table->dropColumn('scheduled_posts_count');
            }

            if (Schema::hasColumn('users', 'photos_count')) {
                $table->dropColumn('photos_count');
            }
        });
    }
};
