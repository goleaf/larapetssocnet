<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\PostReactionSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ReactionVelocityService
{
    public function recordSnapshot(Post $post): void
    {
        $capturedAt = Carbon::now()->startOfMinute();

        PostReactionSnapshot::query()->updateOrCreate(
            [
                'post_id' => $post->getKey(),
                'captured_at' => $capturedAt,
            ],
            [
                'reactions_count' => max(0, (int) $post->getAttribute('reactions_count')),
            ],
        );

        Cache::forget($this->cacheKey($post));
    }

    public function isTrending(Post $post): bool
    {
        return (bool) Cache::remember(
            $this->cacheKey($post),
            now()->addSeconds(60),
            fn (): bool => $this->calculateTrending($post),
        );
    }

    private function calculateTrending(Post $post): bool
    {
        $snapshot = PostReactionSnapshot::query()
            ->where('post_id', $post->getKey())
            ->where('captured_at', '<=', Carbon::now()->subMinutes(5))
            ->latest('captured_at')
            ->first();

        if (! $snapshot instanceof PostReactionSnapshot) {
            return false;
        }

        $delta = max(0, (int) $post->getAttribute('reactions_count') - (int) $snapshot->reactions_count);

        return $delta > 50;
    }

    private function cacheKey(Post $post): string
    {
        return "posts:{$post->getKey()}:reaction-velocity:v1";
    }
}
