<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;

class CounterCacheService
{
    public function rebuildAll(): void
    {
        $this->rebuildFollowCounts();
        $this->rebuildBlockCounts();
        $this->rebuildProfileTabCounts();
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
