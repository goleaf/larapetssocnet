<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CounterCacheService
{
    public function rebuildAll(): void
    {
        $this->rebuildFollowCounts();
        $this->rebuildBlockCounts();
        $this->rebuildProfileTabCounts();
        $this->rebuildProfileActivitySummary();
    }

    public function rebuildFollowCounts(): void
    {
        User::query()
            ->withCount([
                'acceptedFollowers as computed_followers',
                'acceptedFollowing as computed_following',
                'pendingFollowRequests as computed_requests',
            ])
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $user->updateQuietly([
                        'followers_count' => (int) $user->computed_followers,
                        'following_count' => (int) $user->computed_following,
                        'follow_requests_count' => (int) $user->computed_requests,
                    ]);
                }
            });
    }

    public function rebuildBlockCounts(): void
    {
        User::query()
            ->withCount([
                'blocking as computed_blocked_users',
                'blockedBy as computed_blocked_by',
            ])
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $user->updateQuietly([
                        'blocked_users_count' => (int) $user->computed_blocked_users,
                        'blocked_by_count' => (int) $user->computed_blocked_by,
                    ]);
                }
            });
    }

    public function rebuildProfileTabCounts(): void
    {
        User::query()
            ->withCount([
                'media as computed_photos' => fn ($query) => $query
                    ->where('collection_name', User::MEDIA_COLLECTION_PHOTOS),
                'posts as computed_scheduled_posts' => fn ($query) => $query
                    ->where('status', PostStatus::Scheduled->value),
            ])
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $user->updateQuietly([
                        'photos_count' => (int) $user->getAttribute('computed_photos'),
                        'scheduled_posts_count' => (int) $user->getAttribute('computed_scheduled_posts'),
                    ]);
                }
            });
    }

    public function rebuildProfileActivitySummary(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        User::query()
            ->select(['users.id'])
            ->withCount(['posts as computed_posts'])
            ->withSum('posts as computed_post_reactions_received', 'reactions_count')
            ->withSum('posts as computed_post_comments_received', 'comments_count')
            ->withMax('posts as computed_last_post_created_at', 'created_at')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $updates = [];

                    if (Schema::hasColumn('users', 'posts_count')) {
                        $updates['posts_count'] = (int) $user->getAttribute('computed_posts');
                    }

                    if (Schema::hasColumn('users', 'post_reactions_received_count')) {
                        $updates['post_reactions_received_count'] = (int) ($user->getAttribute('computed_post_reactions_received') ?? 0);
                    }

                    if (Schema::hasColumn('users', 'post_comments_received_count')) {
                        $updates['post_comments_received_count'] = (int) ($user->getAttribute('computed_post_comments_received') ?? 0);
                    }

                    if (Schema::hasColumn('users', 'last_post_created_at')) {
                        $updates['last_post_created_at'] = $user->getAttribute('computed_last_post_created_at');
                    }

                    if ($updates !== []) {
                        $user->updateQuietly($updates);
                    }
                }
            });
    }

    public function safeDecrement(Model $model, string $column): void
    {
        if ((int) ($model->getAttribute($column) ?? 0) > 0) {
            $model->decrement($column);
            $model->refresh();
        }
    }

    public function safeIncrement(Model $model, string $column): void
    {
        $model->increment($column);
        $model->refresh();
    }
}
