<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CounterCacheService
{
    public function rebuildAll(): void
    {
        $this->rebuildFollowCounts();
        $this->rebuildBlockCounts();
    }

    public function rebuildFollowCounts(): void
    {
        User::query()
            ->withCount([
                'followers as computed_followers',
                'following as computed_following',
            ])
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $user->updateQuietly([
                        'followers_count' => (int) $user->computed_followers,
                        'following_count' => (int) $user->computed_following,
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
